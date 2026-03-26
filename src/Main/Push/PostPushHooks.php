<?php

namespace Yashus\WPD\Main\Push;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use Yashus\WPD\Archive\Archive;
use Yashus\WPD\Env\Env;
use Yashus\WPD\Files\FileInjector;
use Yashus\WPD\Files\FileNotFoundException;
use Yashus\WPD\Main\AbstractMain;
use Yashus\WPD\Process\Process;
use Yashus\WPD\SSH\SSH;
use Yashus\WPD\Types\Push\PushOptions;
use Yashus\WPD\Types\YASWPD\EnvSettings;
use Yashus\WPD\Types\YASWPD\FileObject;
use Yashus\WPD\Utils\Utils;
use Yashus\WPD\Wordpress\DBLocal;
use Yashus\WPD\Types\YASWPD\Settings;
use Yashus\WPD\Types\YASWPD\FileSettings;
use Yashus\WPD\Wordpress\WPRemote;

class PostPushHooks extends AbstractHook
{

    private SSH $ssh;

    /**
     * @param DBLocal $dbLocal
     * @param Settings $settings
     * @param EnvSettings $remote (staging or production)
     * @param string $dbExportFilename
     * 
     */
    public function __construct(SSH $ssh,  array $hookArgs)
    {
        parent::__construct(...$hookArgs);
        $this->ssh = $ssh;
    }

    public function run()
    {
        // 1. custom composer install

        // 2. Inject env files back to local
        $this->injectEnvFiles();
        // 3. clear cache
        $this->clearCache();
        // 4. run any custom bash commands (todo much later)
        $this->runHookScripts(Hooks::postPush);
    }

    private function injectEnvFiles()
    {
        $fileInjector = new FileInjector($this->settings, $this->output);
        $fileInjector->run(new Env('local'));
        $this->logAction(HookActions::HAS_INJECTED_ENV_FILES, false);
    }

    private function clearCache()
    {
        $ssh = $this->ssh->connect();
        $wpRemote = new WPRemote($this->settings, $this->remote);
        $wpRemote->flushCache($ssh);
    }
}
