<?php


namespace Yashus\WPD\SSH;

use Exception;
use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\System\SSH\Agent;

class SSH
{

    private SSH2 $ssh;
    public SSHConfig $config;

    // $ssh->getExitStatus()
    public function __construct(SSHConfig $config)
    {
        $this->config = $config;
    }




    /**
     * Login via ssh-agent and create a new connection
     * Note: the connection persists until explicitly disconnected or the script ends
     * @return SSH 
     * @throws \Exception - when not authenticated
     */
    public function login(): SSH
    {
        // TODO: log connecting to host
        $agent = new Agent;
        $this->ssh = new SSH2($this->config['host'], $this->config['port']);
        $this->setTimeout(20);
        if (!$this->ssh->login($this->config['user'], $agent)) {
            throw new \Exception('Login failed. Have you added your ssh identity via ssh-add?');
        }

        return $this;
    }

    public function connect(): SSH
    {
        // start a new connection if phpseclib has not been instantiated or connection has been lost
        if (empty($this->ssh) || !$this->isConnected(0)) {
            return $this->login();
        }
        // otherwise, return already connected SSH
        // return SSH if already connected
        return $this;
    }

    public function disconnect(): SSH
    {
        if (isset($this->ssh)) {
            $this->ssh->disconnect();
        }
        return $this;
    }



    public function setTimeout(mixed $timeout)
    {
        $this->ssh->setTimeout($timeout);
        return $this;
    }


    /**
     * Verifies the authentication
     * Tests a ssh connection then disconnects
     * @return bool 
     */
    public function verifySSHAgentAuthentication(): bool
    {
        $isConnected = false;
        try {
            $this->login();
            $isConnected = $this->ssh->isConnected(0);
        } catch (\Exception $e) {
            // do nothing
        }
        $this->ssh->disconnect();

        return $isConnected;
    }

    public function getSSHClient(): SSH2
    {
        return $this->ssh;
    }


    /** Convenience methods may not be necessary, since they duplicate phpseclib methods */

    public function isConnected(int $level = 0)
    {
        return $this->ssh->isConnected($level);
    }

    public function getConfig(): SSHConfig
    {
        return $this->config;
    }

    /**
     * Exec 
     * @return bool
     * @throws SSHProcessFailedException - if process fails
     */
    public function exec($cmd, mixed &$output = null,  &$exit_code = 1, ?callable $callback = null): bool
    {
        if (!isset($this->ssh)) {
            $this->connect();
        }
        $output =  $this->ssh->exec($cmd, $callback);
        $exit_code = $this->ssh->getExitStatus(); // return false or int
        if ($exit_code !== 0) {
            throw new SSHProcessFailedException($exit_code, $cmd, $output);
        }
        return true;
    }
}
