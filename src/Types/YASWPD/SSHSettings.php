<?php

namespace Yashus\WPD\Types\YASWPD;

use Yashus\WPD\Env\Env;
use Yashus\WPD\SSH\SSHConfig;

class SSHSettings extends AbstractEnvSettings
{

    public function __construct(array $params)
    {
        parent::__construct($params, SSHConfig::class);
    }
}
