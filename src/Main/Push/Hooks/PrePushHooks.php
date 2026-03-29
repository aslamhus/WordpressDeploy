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

        parent::__construct(...$hookArgs);
    }

    public function run(): PrePushHooks
    {

        Console::header('Pre-push hooks');
        try {
            // 1. optionally run hooks
            $this->runHookScripts(Hooks::prePush);
            // 2. Prepare and export database (skip if user has opted not to push db)

            // 3. inject env files

        } catch (\Exception $e) {
            // if any error occurs, we have to reverse any changes 
            // 1. perform a search replace back to local values
            throw $e;
        }
        return $this;
    }
}
