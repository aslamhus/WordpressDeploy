<?php


namespace Yashus\WPD\SSH;

use phpseclib3\Net\SSH2;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\System\SSH\Agent;
use Yashus\WPD\Types\YASWPD\SSHSettings;

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
     * @return SSH2 
     * @throws \Exception - when not authenticated
     */
    public function login(): SSH2
    {
        // TODO: log connecting to host
        $agent = new Agent;
        $this->ssh = new SSH2($this->config['host'], $this->config['port']);
        $this->ssh->setTimeout(20);
        if (!$this->ssh->login($this->config['user'], $agent)) {
            throw new \Exception('Login failed. Have you added your ssh identity via ssh-add?');
        }

        return $this->ssh;
    }

    public function connect(): SSH2
    {
        return $this->login();
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
            $this->ssh = $this->login();
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
}
