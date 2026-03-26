<?php

namespace Yashus\WPD\Types\YASWPD;

use Yashus\WPD\Env\Env;

class FileSettings implements \JsonSerializable, \ArrayAccess
{

    private array $data;


    public function __construct(array $params)
    {
        list($filename, $env) = $params;
        $this->data[0]  = $filename;
        $this->data[1] = [
            'directory' => $env['directory'] ?? '',
            'local' => $env['local'] ?? '',
            'production' => $env['production'] ?? '',
            'staging' => $env['staging'] ?? '',
        ];
    }



    public function getDirectory(): string
    {
        return $this->data[1]['directory'] ?? "";
    }

    public function getFileToReplace(): string
    {
        return $this->data[0];
    }

    public function getEnvFilename(Env $env)
    {
        return $this->data[1][$env->__toString()];
    }
    public function jsonSerialize(): array
    {
        return $this->data;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): string
    {
        return $this->data[$offset] ?? '';
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void {}
}
