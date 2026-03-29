<?php

namespace Yashus\WPD\Types\Push;

class PushTypes
{

    public const allowedTypes = [
        "*",
        "wp-content",
        "db",
        "composer",
    ];

    public array $types = [];



    public function __construct(array $types)
    {
        // get the types included by the push cli options
        $this->types = array_filter($types, function ($type) {
            return $type === true;
        });
        // if none are included, default to "*" (everything)

    }

    public function toArray(): array
    {
        return $this->types;
    }
}
