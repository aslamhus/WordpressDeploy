<?php

namespace Yashus\WPD\SSH;

use Yashus\WPD\Env\Env;
use Yashus\WPD\Types\YASWPD\AbstractArraySettings;

class SSHConfig  extends AbstractArraySettings
{

    public Env $env;

    public function __construct(array $params, $env)
    {

        $this->data['host'] = $params['host'] ?? throw new \Exception('SSH host not set');
        $this->data['user'] = $params['user']  ?? throw new \Exception('SSH user not set');
        $this->data['port'] = $params['port'] ?? 22;
        $this->env = $env;
    }

    public function getSSHLogin()
    {
        return "{$this->data['user']}@{$this->data['host']}";
    }
}
