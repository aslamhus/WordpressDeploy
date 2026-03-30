<?php


namespace Yashus\WPD\Main\Push\Hooks;

use SebastianBergmann\CodeCoverage\Node\File;
use Yashus\WPD\Env\Env;
use Yashus\WPD\Archive\ArchiveLocal;
use Yashus\WPD\Archive\ArchiveRemote;
use Yashus\WPD\Console\Console;
use Yashus\WPD\Files\FileInjector;
use Yashus\WPD\Process\Process;
use Yashus\WPD\Rsync\Rsync;
use Yashus\WPD\Rsync\RsyncOptions;
use Yashus\WPD\Wordpress\WPRemote;
use Yashus\WPD\SSH\SSH;

class PushArchiveHook extends AbstractHook
{

    public bool $hasInjectedEnvFiles = false;
    public SSH $ssh;
    public FileInjector $fileInjector;


    public function __construct(SSH $ssh, HookArgs $hookArgs)
    {
        parent::__construct($hookArgs);
        $this->fileInjector = new FileInjector($this->settings, $this->output);
        $this->ssh = $ssh;
    }


    public function run(): PushArchiveHook
    {
        Console::header('Push Site Files (Archive)');



        try {

            $this->injectEnvFiles();
            $archiveFilepath = $this->archive();
            $this->verifyWPConfigHasCorrectDB();
            $this->pushArchiveToRemote($archiveFilepath);
            $this->unzipArchiveOnRemote();
            $this->injectEnvFiles(['revert' => true]);
            $this->output->writeln("✔️ Archive succesfully pushed");
        } catch (\Exception $e) {
            $this->cleanup();
            throw $e;
        }
        return $this;
    }

    public function injectEnvFiles(?array $options = ['revert' => false])
    {
        if (!isset($options['revert'])) {
            throw new \Exception('injectEnvFiles argument must be null, or include an array with boolean revert property');
        }
        $revert = $options['revert'];
        $this->fileInjector->run($revert ?  new Env('local') : $this->env);
        $this->hasInjectedEnvFiles = !$revert;
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
        if ($this->options->interactive && $archive->previousArchiveExists()) {
            if ($archive->verifyIntegrity($this->archiveFilename)) {
                $this->output->writeln("A previous archive with the name {$this->archiveFilename} already exists.");
                if (!Console::confirm("Would you like to overwrite it?")) {
                    return $archive->getArchiveFilepath();
                }
            }
        }
        // create the archive
        $archive->create($output, $exit_code);
        // output the tar command
        $this->output->writeln("Tar command: " . $archive->getTarCmd());
        // print success message
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
     * Before pushing, make sure the database name in the wp-config file
     * matches the env settings database name.
     * @return void 
     */
    private function verifyWPConfigHasCorrectDB()
    {
        // skip verification if wp-config is not included in files array
        if (empty($this->settings->files->hasFile('wp-config.php'))) {
            return true;
        }
        $env_db_name = escapeshellarg($this->settings->getEnvSettings($this->env)['db']);
        $wp_config_path = escapeshellarg($this->settings->getEnvSettings(new Env('local'))->getPublicPath() . DIRECTORY_SEPARATOR . 'wp-config.php');
        try {
            Process::fromShellCommandLine("cat $wp_config_path | grep $env_db_name", $output, $exit_code, true);
        } catch (\Exception $e) {
            throw new \Exception("injected wp-config.php did not have the correct database name ($env_db_name) for " . $this->env->__toString() . '. Please check your .yaswpd.json settings. ' . $e->getMessage());
        }
        $this->output->writeln('✔️ Verified injected wp-config.php file for ' . $this->env->__toString());

        return true;
    }

    public function cleanup(): void
    {
        if ($this->hasInjectedEnvFiles) {
            $this->injectEnvFiles(['revert' => true]);
        }
    }
}
