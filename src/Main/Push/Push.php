<?php

namespace Yashus\WPD\Main\Push;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yashus\WPD\Console\Console;
use Yashus\WPD\Docker\DockerUtils;
use Yashus\WPD\Env\Env;
use Yashus\WPD\Main\AbstractMain;
use Yashus\WPD\Main\Push\Hooks\ClearCacheHook;
use Yashus\WPD\Main\Push\Hooks\ComposerPushHook;
use Yashus\WPD\Main\Push\Hooks\HookArgs;
use Yashus\WPD\Main\Push\Hooks\HookInterface;
use Yashus\WPD\Main\Push\Hooks\PostPushHooks;
use Yashus\WPD\Main\Push\Hooks\PrePushHooks;
use Yashus\WPD\Main\Push\Hooks\PushArchiveHook;
use Yashus\WPD\Main\Push\Hooks\PushDBHook;
use Yashus\WPD\Main\Push\Hooks\RsyncSiteHook;
use Yashus\WPD\SSH\SSH;
use Yashus\WPD\Types\Push\PushOptions;
use Yashus\WPD\Types\YASWPD\EnvSettings;
use Yashus\WPD\Utils\Utils;
use Yashus\WPD\Types\YASWPD\Settings;

class Push extends AbstractMain
{

    private Env $env;
    private SSH $ssh;
    private Settings $settings;
    private EnvSettings $remote;
    private string $release_id;
    private string $src;
    private string $deployIgnore;
    private string $dest;
    private string $db;
    private string $remoteUrl;
    private string $dbExportFilename;
    private string $archiveFilename;
    private PushOptions $options;
    private PushDBHook $pushDBHook;
    private HookInterface $pushArchiveHook;

    public function __construct(Env $env, Settings $settings, SSH $ssh, PushOptions $options, InputInterface $input,  OutputInterface $output, SymfonyStyle $io)
    {
        parent::__construct($input, $output, $io);
        $this->env = $env;
        $this->settings = $settings;
        $this->ssh = $ssh;
        if (empty($settings->getEnvSettings($this->env))) {
            throw new \Exception("Invalid wpd.json setting. Expected property name corresponding to env: '{$this->env}'");
        }
        $this->remote = $settings->getEnvSettings($this->env); // staging || production
        $this->release_id = Utils::getTodayFormatted();
        $this->options = $options;
        $this->src = $this->settings->local->getPublicPath();
        $this->dest = $this->remote->getPublicPath();
        $this->db = $this->remote['db'];
        $this->remoteUrl = $this->remote['url'];
        $this->deployIgnore =  $this->settings->local['root'] . DIRECTORY_SEPARATOR .  ".deployignore";
        if (!file_exists($this->deployIgnore)) {
            throw new \Exception("Could not find deploy ignore file at '{$this->deployIgnore}'. Please make sure you add a .deployginore file in your root directory.");
        }
        $this->dbExportFilename = $this->release_id . '.sql';
        $this->archiveFilename = $this->release_id . '.tar.gz';
    }



    public function run()
    {
        $this->printTitle();
        DockerUtils::verifyContainerIsRunning();
        if (!$this->verifyPushSettings())  return;
        if ($this->options->interactive) {
            $this->interactToGetPushOptions();
        }


        $hookArgs = new HookArgs($this->env,  $this->settings, $this->remote, $this->options, $this->output, $this->input, $this->io, $this->dbExportFilename, $this->archiveFilename, $this->deployIgnore);
        // run any pre push hooks
        if ($this->options->prePushHooks) {
            (new PrePushHooks($hookArgs))->run();
        }
        // 1. push archive or rsync files 
        if ($this->options->pushArchive) {
            $this->pushArchive($hookArgs)->run();
        }
        // 2. export and push db
        if ($this->options->pushDb) {
            $this->pushDBHook = (new PushDBHook($this->ssh, $hookArgs))->run();
        }
        // 3. push and install composer
        if ($this->options->pushComposer) {
            (new ComposerPushHook($this->ssh, $hookArgs))->run();
        }
        // 4. clear cache
        if ($this->options->flushCache) {
            (new ClearCacheHook($this->ssh, $hookArgs))->run();
        }
        if ($this->options->postPushHooks) {
            (new PostPushHooks($hookArgs))->run();
        }
        $this->printSuccessMsg();
    }

    private function pushArchive($hookArgs): HookInterface
    {
        switch ($this->settings->upload_type) {
            case 'rsync':
                return new RsyncSiteHook($this->ssh, $hookArgs);
            case 'archive':

                return new  PushArchiveHook($this->ssh, $hookArgs);
            default:
                throw new \Exception("Unknown upload type " . $this->settings->upload_type . ". Expected 'rsync' or 'archive'");
        }
    }

    private function interactToGetPushOptions()
    {
        $this->options->pushArchive = Console::confirm("Do you want to push wp-content to the {$this->env} server?");
        $this->options->pushDb = Console::confirm("Do you want to push the database to the {$this->env} server?");
        $this->options->pushComposer = Console::confirm("Do you want to push and install composer.json to the {$this->env} server?");
        if ($this->options->pushDb || $this->options->pushArchive) {
            $this->options->flushCache = Console::confirm("Do you want to flush the wordpress cache after pushing to the {$this->env} server?");
        }
    }

    private function printTitle()
    {
        $this->io->title(strtoupper("PUSHING to " . $this->env->outputTitle()));
    }

    private function printSuccessMsg()
    {
        $this->output->writeln("");
        $this->output->writeln("🎉 <info>" . $this->settings->project . " was pushed to {$this->env} successfully!</info>");
        foreach ($this->options as $option => $shouldPerform) {
            if ($shouldPerform) {
                if ($option == 'interactive') continue;
                // get option title by splitting camel case array key, i.e. 'pushArchive'
                $split = preg_split('/(?=[A-Z])/', $option);
                // make first word past tense
                $split = array_map('strtolower', $split);
                $this->output->writeln('✔️ ' . implode(' ', $split));
            }
        }
    }

    private function verifyPushSettings()
    {
        // skip when --no-interaction option set
        if (!$this->options->interactive) return true;
        $this->output->writeln('Please verify your settings');
        $this->io->definitionList(
            ['src' => $this->src],
            ['destination' => $this->dest],
            ['database' => $this->db],
            ['url' => $this->remoteUrl]
        );
        return Console::confirm("Are these correct?");
    }

    public function handleSigint(int $signo, mixed $siginfo)
    {
        $this->output->writeln("");
        $this->output->writeln("<error>Cancelled.</error>");
        $this->output->writeln("Cleaning up....");

        isset($this->pushArchiveHook) && $this->pushArchiveHook->cleanup();
        isset($this->pushDBHook) && $this->pushDBHook->cleanup();
    }
}
