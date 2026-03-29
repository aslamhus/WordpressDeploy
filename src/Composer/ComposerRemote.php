<?php

namespace Yashus\WPD\Composer;

use Yashus\WPD\Env\Env;
use Yashus\WPD\Rsync\Rsync;
use Yashus\WPD\SSH\SSH;
use Yashus\WPD\Types\YASWPD\ComposerConfig;
use Yashus\WPD\Types\YASWPD\ComposerSettings;

class ComposerRemote
{

    public ComposerSettings $composerSettings;
    public Env $remoteEnv;
    public function __construct(ComposerSettings $composerSettings, Env $remoteEnv)
    {
        $this->composerSettings = $composerSettings;
        $this->remoteEnv = $remoteEnv;
    }

    public function install(SSH $ssh)
    {
        // 1. upload composer.json to specified directory
        $this->verifyLocalComposerJsonExists();
        $localPath = $this->composerSettings->getEnvConfig(new Env('local'))['json'];

        $remotePath = $this->composerSettings->getEnvConfig($this->remoteEnv)['json'];
        $this->uploadComposerJson($localPath, $remotePath, $ssh);
        // run composer install on remote

        $this->installComposer($remotePath, $ssh);
    }

    public function uploadComposerJson(string $localPath, string $remotePath, SSH $ssh)
    {
        $config = $ssh->getConfig();
        $user = $config['user'];
        $host = $config['host'];
        $src = $localPath;
        $dest = "$user@$host:$remotePath";
        return Rsync::run($src, $dest);
    }

    public function verifyLocalComposerJsonExists()
    {
        $localPath = $this->composerSettings->getEnvConfig(new Env('local'))['json'];
        if (!file_exists($localPath)) {
            throw new \Exception("Local composer.json does not exist at $localPath");
        }
        return true;
    }


    public function verifyRemoteComposerJsonExists(SSH $ssh)
    {
        $remotePath = $this->composerSettings->getEnvConfig($this->remoteEnv)['json'];
        $remotePath = escapeshellarg($remotePath);
        try {
            return $ssh->exec("[[ -f $remotePath ]] || exit 1");
        } catch (\Exception $e) {
            throw new \Exception("Remote composer.json does not exist for {$this->remoteEnv} at $remotePath");
        }
        return true;
    }

    /**
     * Runs command: composer require <options>
     * options are set in yaswpd.json under composer.[env].install
     * @param ComposerConfig $remoteConfig 
     * @param SSH $ssh 
     * @return void 
     */
    private function installComposer(ComposerConfig $remoteConfig, SSH $ssh)
    {
        $ssh =  $ssh->connect();
        $remotePath = escapeshellarg($remoteConfig['path']);
        $cmd = implode(" ", [
            ...$remoteConfig['cmd'], // composer or phar.composer etc.
            'require',
            ...$remoteConfig['install'] ?? [],
        ]);

        try {
            $ssh->exec("cd $remotePath && $cmd", null, $exit_code);
        } catch (\Exception $e) {
            throw new \Exception("Composer install failed at $remotePath", $exit_code, $e);
        }
    }
}
