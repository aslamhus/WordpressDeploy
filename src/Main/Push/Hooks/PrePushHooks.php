<?php

namespace Yashus\WPD\Main\Push\Hooks;

use Yashus\WPD\Console\Console;

class PrePushHooks extends AbstractHook
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

    public function run(): PrePushHooks
    {

        $hookName = Hooks::prePush;
        if (!$this->hasHooks($hookName)) return $this;
        Console::header('Pre-push hooks');
        $scripts = $this->runHookScripts($hookName);
        return $this;
    }

    public function cleanup(): void {}
}
