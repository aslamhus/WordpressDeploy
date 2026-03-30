<?php



namespace Yashus\WPD\Main\Push\Hooks;

use Yashus\WPD\Composer\ComposerRemote;
use Yashus\WPD\Console\Console;
use Yashus\WPD\SSH\SSH;
use Yashus\WPD\Wordpress\WPRemote;

class ClearCacheHook extends AbstractHook
{

    public SSH $ssh;

    public function __construct(SSH $ssh,  HookArgs $hookArgs)
    {
        parent::__construct($hookArgs);
        $this->ssh = $ssh;
    }

    public function run(): ClearCacheHook
    {
        Console::header('Flush cache');
        $ssh = $this->ssh->connect();
        $wpRemote = new WPRemote($this->settings, $this->remote);
        $wpRemote->flushCache($ssh);
        $this->output->writeln('✔️ Cache succesfully flushed');
        return $this;
    }

    public function cleanup(): void {}
}
