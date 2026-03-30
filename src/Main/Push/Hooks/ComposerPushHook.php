<?php



namespace Yashus\WPD\Main\Push\Hooks;

use Yashus\WPD\Composer\ComposerRemote;
use Yashus\WPD\Console\Console;
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


        if (!isset($this->settings->composer)) {
            return $this;
        }
        Console::header('Composer push');

        $composer = new ComposerRemote($this->settings->composer, $this->env);
        $composer->verifyLocalComposerJsonExists();
        $this->output->writeln("Pushing composer.json to remote");
        $composer->uploadComposerJson($this->ssh);
        $this->output->writeln("installing composer on remote");
        $composer->installComposer($this->ssh);
        $this->output->writeln('✔️ Composer succesfully pushed and installed');
        return $this;
    }

    public function cleanup(): void {}
}
