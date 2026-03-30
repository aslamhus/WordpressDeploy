<?php

namespace Yashus\WPD\Main\Push\Hooks;

use Yashus\WPD\Console\Console;
use Yashus\WPD\SSH\SSH;
use Yashus\WPD\Types\YASWPD\EnvSettings;
use Yashus\WPD\Wordpress\DBLocal;
use Yashus\WPD\Types\YASWPD\Settings;

class PostPushHooks extends AbstractHook
{


    /**
     * @param DBLocal $dbLocal
     * @param Settings $settings
     * @param EnvSettings $remote (staging or production)
     * @param string $dbExportFilename
     * 
     */
    public function __construct(HookArgs $hookArgs)
    {
        parent::__construct($hookArgs);
    }



    public function run(): PostPushHooks
    {
        $hook = Hooks::postPush;
        if (!$this->hasHooks($hook)) return $this;
        Console::header('Post-push hooks');
        $scripts = $this->runHookScripts($hook);
        return $this;
    }

    public function cleanup(): void {}
}
