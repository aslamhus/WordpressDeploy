<?php



namespace Yashus\WPD\Main\Push\Hooks;

use Yashus\WPD\Composer\ComposerRemote;
use Yashus\WPD\SSH\SSH;

class ComposerPushHook extends AbstractHook
{

    public SSH $ssh;

    public function __construct(SSH $ssh,  HookArgs $hookArgs)
    {
        parent::__construct($hookArgs);
        $this->ssh = $ssh;
    }

    public function run(): ComposerPushHook
    {

        if (!isset($this->settings->composer)) return $this;
        $composerRemote = new ComposerRemote($this->settings->composer, $this->env);
        $composerRemote->install($this->ssh);
        return $this;
    }
}
