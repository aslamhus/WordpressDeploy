<?php

namespace Yashus\WPD\Types\YASWPD;

class EnvSettings implements \JsonSerializable, \ArrayAccess
{

    public array $data;


    public function __construct(
        array $params
    ) {
        $this->data['root'] = $params['root'];
        $this->data['public'] = $params['public'];
        $this->data['tmp'] = $params['tmp'];
        $this->data['url'] = $params['url'];
        $this->data['db'] = $params['db'];
    }

    /**
     * Returns <root>/<public>
     * @return string 
     */
    public function getPublicPath(): string
    {
        return $this->data['root'] . DIRECTORY_SEPARATOR . $this->data['public'];
    }
    /**
     * Defines the data serialized by json_encode().
     *
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }
    /**
     * @param mixed $offset
     * @throws \InvalidArgumentException on unknown key
     */
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->data);
    }

    /**
     * @param  mixed $offset
     * @return string
     * @throws \InvalidArgumentException on unknown key
     */
    public function offsetGet(mixed $offset): string
    {
        if (!$this->offsetExists($offset)) {
            throw new \InvalidArgumentException(
                "EnvSettings: unknown key \"{$offset}\"."
            );
        }
        return $this->data[$offset];
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @throws \InvalidArgumentException on unknown key or non-string value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!$this->offsetExists($offset)) {
            throw new \InvalidArgumentException(
                "EnvSettings: unknown key \"{$offset}\"."
            );
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException(
                "EnvSettings: key \"{$offset}\" must be a string, " . gettype($value) . " given."
            );
        }
        $this->data[$offset] = $value;
    }

    /**
     * Unsetting a key is not permitted on a typed Settings.
     *
     * @throws \RuntimeException always
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new \RuntimeException(
            "EnvSettings: unsetting keys is not allowed."
        );
    }
}
