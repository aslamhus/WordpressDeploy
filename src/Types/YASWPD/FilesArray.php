<?php

namespace Yashus\WPD\Types\YASWPD;

use JsonSerializable;

/** @template-implements IteratorAggregate<FileObject> */
class FilesArray implements \IteratorAggregate, \ArrayAccess, JsonSerializable
{

    public array $files;


    /**
     * @param Array<FileSettings> 
     */
    public function __construct(array $files)
    {
        $this->files = $files;
        foreach ($files as $index => $file) {
            if (!($file instanceof FileSettings)) {
                $this->files[$index] = new FileSettings($file);
            }
        }
    }

    public function hasFile(string $filename): ?FileSettings
    {

        return  array_find($this->files, function ($file, $key) use ($filename) {
            /** $@var FileSettings */
            return $file->getFileToReplace() === $filename;
        });
    }

    public function jsonSerialize(): mixed
    {
        return $this->files;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->files[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->files[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->files[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->files[$offset]);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->files);
    }
}
