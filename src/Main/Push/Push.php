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
use Yashus\WPD\Main\Push\Hooks\PostPushHooks;
use Yashus\WPD\Main\Push\Hooks\PrePushHooks;
use Yashus\WPD\Main\Push\Hooks\PushArchiveHook;
use Yashus\WPD\Main\Push\Hooks\PushDBHook;
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
    private string $deploy_ignore;
    private string $dest;
    private string $db;
    private string $remoteUrl;
    private string $dbExportFilename;
    private string $archiveFilename;
    private PushOptions $options;
    private PushDBHook $pushDBHook;
    private PushArchiveHook $pushArchiveHook;

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
        $this->printPushTypeTitle();
        DockerUtils::verifyContainerIsRunning();
        if (!$this->verifyPushSettings())  return;
        if ($this->options->interaction) {
            $this->interactToGetPushOptions();
        }
        $hookArgs = new HookArgs($this->env,  $this->settings, $this->remote, $this->options, $this->output, $this->input, $this->io, $this->dbExportFilename, $this->archiveFilename);
        // run any pre push hooks
        (new PrePushHooks($hookArgs))->run();
        // 1. archive and push site
        if ($this->options->shouldPushArchive) {
            $this->pushArchiveHook = (new PushArchiveHook($this->ssh, $hookArgs))->run();
        }
        // 2. export and push db
        if ($this->options->shouldPushDb) {
            $this->pushDBHook = (new PushDBHook($this->ssh, $hookArgs))->run();
        }
        // 3. push and install composer
        if ($this->options->shouldPushComposer) {
            (new ComposerPushHook($this->ssh, $hookArgs))->run();
        }
        // 4. clear cache
        if ($this->options->shouldFlushCache) {
            (new ClearCacheHook($this->ssh, $hookArgs))->run();
        }
        // run any post push hooks
        (new PostPushHooks($hookArgs))->run();
        $this->output->writeln("🎉 " . $this->settings->project . " was pushed to {$this->env} successfully!");
    }

    private function interactToGetPushOptions()
    {
        $this->options->shouldPushArchive = Console::confirm("Do you want to push wp-content to the {$this->env} server?");
        $this->options->shouldPushDb = Console::confirm("Do you want to push the database to the {$this->env} server?");
        $this->options->shouldPushComposer = Console::confirm("Do you want to push composer {$this->env} server?");
        if ($this->options->shouldPushDb || $this->options->shouldPushArchive) {
            $this->options->shouldFlushCache = Console::confirm("Do you want to flush the wordpress cache after pushing? {$this->env} server?");
        }
    }

    private function printPushTypeTitle()
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
        return Console::confirm("Are these correct?");
    }

    public function handleSigint(int $signo, mixed $siginfo)
    {
        $this->output->writeln("");
        $this->output->writeln("<error>Cancelled.</error>");
        $this->output->writeln("Cleaning up....");
        if ($this->pushArchiveHook->hasInjectedEnvFiles) {
            $this->pushArchiveHook->injectEnvFiles(['revert' => true]);
        }
        if ($this->pushDBHook->hasSearchReplacedDb) {
            $this->pushDBHook->searchReplaceDB(['revert' => true]);
        }
    }
}
