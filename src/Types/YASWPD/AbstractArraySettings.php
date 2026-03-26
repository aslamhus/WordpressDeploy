<?php


namespace Yashus\WPD\Types\YASWPD;


use ArrayAccess;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

abstract class AbstractArraySettings implements ArrayAccess, JsonSerializable, IteratorAggregate
{
    public array $data = [];

    public function jsonSerialize(): mixed
    {
        return $this->data;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->data);
    }
}
