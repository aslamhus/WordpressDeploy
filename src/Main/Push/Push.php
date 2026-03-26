<?php

namespace Yashus\WPD\Main\Push;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Yashus\WPD\Archive\ArchiveLocal;
use Yashus\WPD\Archive\ArchiveRemote;
use Yashus\WPD\Console\Console;
use Yashus\WPD\Docker\DockerUtils;
use Yashus\WPD\Env\Env;
use Yashus\WPD\Files\FileInjector;
use Yashus\WPD\Main\AbstractMain;
use Yashus\WPD\Rsync\Rsync;
use Yashus\WPD\Rsync\RsyncOptions;
use Yashus\WPD\SSH\SSH;
use Yashus\WPD\Types\Push\PushOptions;
use Yashus\WPD\Types\YASWPD\EnvSettings;
use Yashus\WPD\Utils\Utils;
use Yashus\WPD\Wordpress\DBLocal;
use Yashus\WPD\Wordpress\DBRemote;
use Yashus\WPD\Types\YASWPD\Settings;
use Yashus\WPD\Wordpress\WPRemote;


class Push extends AbstractMain
{

    private Env $env;
    private SSH $ssh;
    private DBRemote $dbRemote;
    private DBLocal $dbLocal;
    private Settings $settings;
    private EnvSettings $remote;
    private string $release_id;
    private string $src;
    private string $deploy_ignore;
    private string $dest;
    private string $db;
    private string $remoteUrl;
    private string $dbExportFilename;
    private string $archiveFilename;
    private bool $hasInjectedEnvFiles = false;
    private bool $hasExportedDb = false;
    private bool $hasSearchReplacedDb = false;
    private PushOptions $options;
    private PrePushHooks $prePush;
    private PostPushHooks $postPush;


    public function __construct(Env $env, Settings $settings, SSH $ssh, PushOptions $options, InputInterface $input,  OutputInterface $output, SymfonyStyle $io)
    {
        parent::__construct($input, $output, $io);
        $this->env = $env;
        $this->settings = $settings;
        $this->ssh = $ssh;
        if (empty($settings->getEnvSettings($this->env))) {
            throw new \Exception("Invalid .yaswpd.json setting. Expected property name corresponding to env: '{$this->env}'");
        }
        $this->remote = $settings->getEnvSettings($this->env); // staging || production
        $this->release_id = Utils::getTodayFormatted();
        $this->options = $options;
        $this->init($settings);
    }




    private function init($settings)
    {
        $this->dbRemote = new DBRemote(([
            'wpcli' => $settings['wpcli']['remote'],
            'wp_dir' => $this->remote['root'] . DIRECTORY_SEPARATOR . $this->remote['public']
        ]));
        // get local wordpress directory where wpcli should run
        $local_wp_dir = $this->settings->local->getPublicPath();
        $wpcli =  $settings['wpcli']['local'];
        // docker settings
        if ($this->settings->docker->isUsingDocker()) {
            $local_wp_dir = "./";
        }
        $this->dbLocal = new DBLocal([
            'wpcli' => $wpcli,
            'wp_dir' => $local_wp_dir
        ]);
        $this->src = $this->settings->local->getPublicPath();
        $this->dest = $this->remote->getPublicPath();
        $this->db = $this->remote['db'];
        $this->remoteUrl = $this->remote['url'];
        $this->deploy_ignore =  $this->settings->local['root'] . DIRECTORY_SEPARATOR .  ".deployignore";
        if (!file_exists($this->deploy_ignore)) {
            throw new \Exception("Could not find deploy ignore file at '{$this->deploy_ignore}'. Please make sure you add a .deployginore file in your root directory.");
        }
        $this->dbExportFilename = $this->release_id . '.sql';
        $this->archiveFilename = $this->release_id . '.tar.gz';
    }


    public function run()
    {
        // $this->printTitle();
        // DockerUtils::verifyContainerIsRunning();
        // if (!$this->verifyPushSettings())  return;
        // if (!Console::confirm("Do you want to push wp-content to the {$this->env} server?")) {
        //     $this->options->shouldPushArchive = false;
        // };
        // if (!Console::confirm("Do you want to push the database to the {$this->env} server?")) {
        //     $this->options->shouldPushDb = false;
        // };

        $hookArgs = [$this->env, $this->dbLocal, $this->settings, $this->remote, $this->options, $this->output, $this->input, $this->io, $this->dbExportFilename];
        $this->prePush = new PrePushHooks($hookArgs);
        $this->postPush = new PostPushHooks($this->ssh, $hookArgs);
        // 1. search-replace db and export it
        // 2. inject env files
        // 3. verify dbname is correct 
        $this->prePush($hookArgs);
        // 4. archive site and push to remote
        // 5. optionally, import db
        $this->push();
        // 5. custom composer install
        // 6. inject env files back to local
        $this->postPush($hookArgs);
        // report success!
        $this->output->writeln("🎉 " . $this->settings->project . " was pushed to {$this->env} successfully!");
    }

    public function onHookAction(string $action, mixed $payload)
    {
        switch ($action) {
            case HookActions::HAS_INJECTED_ENV_FILES:
                $this->hasInjectedEnvFiles = $payload;
                break;

            case HookActions::HAS_EXPORTED_DB:
                $this->hasExportedDb = $payload;
                break;

            case HookActions::HAS_SEARCH_REPLACED_DB:
                $this->hasSearchReplacedDb = $payload;
                break;

            default:
                throw new \Exception('Unknown action: ' . $action);
        }
    }
    private function printTitle()
    {
        $this->io->title(strtoupper("PUSHING to " . $this->env->outputTitle()));
    }

    private function verifyPushSettings()
    {
        $this->output->writeln('Please verify your settings');
        $this->io->definitionList(

            ['src' => $this->src],
            ['destination' => $this->dest],
            ['database' => $this->db],
            ['url' => $this->remoteUrl]

        );

        return $this->confirm("Are these correct?");
    }

    private function prePush()
    {
        Console::header('Pre-push hooks');
        $this->prePush->setActionCallback([$this, 'onHookAction']);
        $this->prePush->run();
    }

    /**
     * Arcive and send files to remote, then unzip on remote
     * @return void 
     */
    private function push()
    {


        Console::header('Pushing to remote');
        // 1. optionally archive / upload remote
        if ($this->options->getShouldPushArchive()) {
            $archiveFilepath = $this->archive();
            $this->pushArchiveToRemote($archiveFilepath);
            $this->unzipArchiveOnRemote();
        }
        // 2. optionally upload and import db
        if ($this->options->getShouldPushDb()) {
            $this->pushDb();
            $this->importRemoteDb();
        }
    }

    private function postPush()
    {
        Console::header('Post-push hooks');
        $this->postPush->setActionCallback([$this, 'onHookAction']);
        $this->postPush->run();
    }


    /**
     * Archive public directory to current working directory
     * !!Depends on .deployignore file in root from which we get dirs/files to exclude
     * 
     * @return void 
     */
    private function archive(): false|string
    {
        $src = $this->settings->local->getPublicPath();
        $this->output->writeln('<comment>Archiving wordpress site (' . basename($this->settings->local->getPublicPath()) . ')</comment>');
        $archive = new ArchiveLocal($src, $this->archiveFilename, './.deployignore');
        // check if archive exists
        if ($archive->previousArchiveExists()) {
            if ($archive->verifyIntegrity($this->archiveFilename)) {
                if (!Console::confirm("A previous archive with the name {$this->archiveFilename} already exists. Would you like to overwrite it?")) {
                    return $archive->getArchiveFilepath();
                }
            }
        }
        // create the archive
        $archive->create($output, $exit_code);
        if (!empty($archivePath)) {
            $this->output->writeln('<fg=green>✔️ site archived </>');
        }
        return $archive->getArchiveFilepath();
    }

    /**
     * Push archive to remote
     * @param mixed $archiveFilepath 
     * @throws ProcessFailedException
     * @return true 
     */
    private function pushArchiveToRemote($archiveFilepath)
    {
        $config = $this->ssh->getConfig();
        $user = $config['user'];
        $host = $config['host'];
        $src = $archiveFilepath;
        if (empty($src) || is_dir($src)) {
            throw new \Exception("Failed to push archive to remote. Archive file was not a file or was empty:" . $src);
        }
        $dest = "$user@$host:" . $this->remote->getPublicPath();
        $this->output->writeln("<comment>Pushing archive to remote</comment>");
        $this->io->definitionList(
            ['src' => $archiveFilepath],
            ['dest' => $dest]
        );
        return Rsync::run(
            $src,
            $dest,
            new RsyncOptions([
                'dry_run' => false
            ])
        );
    }

    private function unzipArchiveOnRemote()
    {
        $this->output->writeln("<comment>Unzipping archive {$this->archiveFilename} on remote</comment>");
        // remote path will either be staging / production public path
        $archivePath = $this->remote->getPublicPath();
        // make a ssh connection
        $ssh =  $this->ssh->connect();
        // set maintenance mode while unzipping and removing archive
        $wpRemote = new WPRemote($this->settings, $this->remote);
        try {

            $this->output->writeln("<fg=magenta>🛠️ Maintenance mode activated</>");
            $wpRemote->setMaintenanceMode($ssh, true);
            ArchiveRemote::unzip($ssh, $archivePath, $this->archiveFilename);
            $this->output->writeln("<fg=green>✔️ Archive unzipped on remote</>");
            ArchiveRemote::remove($ssh, $archivePath, $this->archiveFilename);
            $this->output->writeln("Archive file removed from remote");
            $wpRemote->setMaintenanceMode($ssh, false);
            $this->output->writeln("<fg=magenta>🛠️ Maintenance mode deactivated</>");
        } catch (\Exception $e) {
            // if there is an exception, we must make sure to reset maintenance mode
            // sleep to avoid phpseclib SSH exception "Close channel before trying to open again" 
            // occurs sometimes with succession of ssh commands
            sleep(1);
            $wpRemote->setMaintenanceMode($ssh, false);
            throw $e;
        }
        $ssh->disconnect();
    }

    /**
     * Push db to tmp dir
     * 
     * @param string $dbExportPath - the absolute path to the exported db file
     * @return true 
     * @throws ProcessFailedException
     */
    public function pushDb(): true
    {
        $config = $this->ssh->getConfig();
        $user = $config['user'];
        $host = $config['host'];
        // check for db filename in docker
        $src = $this->settings->docker->getTmpDirMount() . DIRECTORY_SEPARATOR . $this->dbExportFilename;
        if (!file_exists($src)) {
            throw new \Exception('Failed to push db. Could not find db export at ' . $src);
        }
        $dest = "$user@$host:" . $this->remote->getPublicPath();
        $this->output->writeln("<comment>Pushing DB to remote</comment>");
        $this->io->definitionList(
            ['src' => $src],
            ['dest' => $dest]
        );
        return Rsync::run($src, $dest, new RsyncOptions([
            'dry_run' => false
        ]));
    }

    /**
     * Import remote db 
     * @return true 
     * @throws \RuntimeException
     */
    public function importRemoteDb()
    {
        $ssh = $this->ssh->connect();
        $this->dbRemote->import($ssh, $this->dbExportFilename);
        $this->dbRemote->remove($ssh, $this->remote->getPublicPath() . DIRECTORY_SEPARATOR . $this->dbExportFilename);
        return true;
    }

    public function handleSigint(int $signo, mixed $siginfo)
    {
        // make sure to inject files back to db
        $this->output->writeln("");
        $this->output->writeln("<error>Cancelled.</error>");
        $this->output->writeln("Cleaning up....");
        if ($this->hasInjectedEnvFiles) {
            $fileInjector = new FileInjector($this->settings, $this->output);
            $fileInjector->run(new Env('local'));
        }
        if ($this->hasSearchReplacedDb) {
            $this->prePush->searchReplaceDB(['revert' => true]);
        }
    }
}
