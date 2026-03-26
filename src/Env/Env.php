<?php

namespace Yashus\WPD\Env;


class Env implements \JsonSerializable
{


    public string $env;
    const array type = [
        'staging' => 'staging',
        'production' => 'production',
        'local' => 'local'
    ];
    const array colors = [
        self::type['production'] => 'magenta',
        self::type['staging'] => 'cyan',
        self::type['local'] => 'white'
    ];

    public function __construct(string $env)
    {
        if (!isset(self::type[$env])) {
            throw new \InvalidArgumentException("Invalid environment argument. Accepts " . implode(',', array_values(self::type)));
        }
        $this->env = $env;
    }


    public function outputTitle()
    {
        $color = self::colors[$this->env];
        return "<fg={$color}>{$this->env}</>";
    }

    public function jsonSerialize(): mixed
    {
        return $this->env;
    }

    public function __toString()
    {
        return $this->env;
    }
}
