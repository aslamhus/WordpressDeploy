<?php

namespace Yashus\WPD\Types\YASWPD;

use Yashus\WPD\Env\Env;

abstract class AbstractEnvSettings extends AbstractArraySettings
{



    public function __construct(array $params, string $configClass)
    {
        foreach ($params as $env => $config) {
            if (!in_array($env, Env::type)) {

                throw new \Exception("Invalid env type '$env' in " . $this->getShortName() . ". Expected " . implode(", ", Env::type));
            }
            $this->data[$env] = new $configClass($config, new Env($env));
        }
    }

    private function getShortName(): string
    {
        $reflect = new \ReflectionClass(get_class($this));
        return $reflect->getShortName();
    }

    public function getEnvConfig(Env $env): mixed
    {
        return $this->data[$env->__toString()] ?? throw new \Exception("No " . $this->getShortName() . " config found for $env");
    }
}
