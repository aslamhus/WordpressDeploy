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
    public ComposerConfig $local;
    public ComposerConfig $remote;
    public function __construct(ComposerSettings $composerSettings, Env $remoteEnv)
    {
        $this->composerSettings = $composerSettings;
        $this->remoteEnv = $remoteEnv;
        $this->local = $this->composerSettings->getEnvConfig(new Env('local'));
        $this->remote = $this->composerSettings->getEnvConfig($this->remoteEnv);
    }

    public function uploadAndInstall(SSH $ssh)
    {
        $this->verifyLocalComposerJsonExists();
        $this->uploadComposerJson($ssh);
        $this->installComposer($ssh);
    }

    public function uploadComposerJson(SSH $ssh)
    {
        $config = $ssh->getConfig();
        $user = $config['user'];
        $host = $config['host'];
        $src = $this->local['json'];
        $dest = "$user@$host:{$this->remote['json']}";
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
    public function installComposer(SSH $ssh)
    {
        $ssh =  $ssh->connect();
        $remotePath = escapeshellarg(dirname($this->remote['json']));
        if (empty($remotePath)) {
            throw new \Exception('Failed to install composer, remote path was empty. Check your .yaswpd.json config');
        }
        $cmd = implode(" ", [
            ...$this->remote['cmd'], // composer or phar.composer etc.
            'update',
            ...$this->remote['install'] ?? [],
        ]);

        try {
            $ssh->exec("cd $remotePath && $cmd", $output, $exit_code);
        } catch (\Exception $e) {
            throw $e;
            // throw new \Exception("Composer install failed at $remotePath", $exit_code, $e);
        }
    }
}
