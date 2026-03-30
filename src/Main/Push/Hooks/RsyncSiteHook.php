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
use Yashus\WPD\Utils\Utils;

class RsyncSiteHook extends AbstractHook
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


    public function run(): self
    {
        Console::header('Push Site Files (Rsync)');
        try {

            $this->injectEnvFiles();
            $this->rsyncPublic();
            $this->injectEnvFiles(['revert' => true]);
            $this->output->writeln("✔️ Archive succesfully pushed");
        } catch (\Exception $e) {
            $this->cleanup();
            throw $e;
        }
        return $this;
    }

    public function rsyncPublic()
    {
        $config = $this->ssh->getConfig();
        $user = $config['user'];
        $host = $config['host'];
        $src = './';
        $cwd = $this->settings->local->getPublicPath();
        $dest = "$user@$host:" . $this->remote->getPublicPath() . '/';
        $exclude = Utils::getExcludesFromFile($this->deployIgnore);
        Rsync::run($src, $dest, new RsyncOptions([
            'exclude' => $exclude,
            'dry_run' => false
        ]), $this->output, $cwd);
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

    public function cleanup(): void
    {
        if ($this->hasInjectedEnvFiles) {
            $this->injectEnvFiles(['revert' => true]);
        }
    }
}
