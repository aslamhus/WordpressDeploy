<?php

namespace Yashus\WPD\Types\Push;

class PushOptions
{

    public bool $shouldPushDb;
    public bool $shouldPushArchive;

    public function __construct(array $params)
    {
        $this->shouldPushDb = $params['shouldPushDb'] ?? false;
        $this->shouldPushArchive = $params['shouldPushArchive'] ?? false;
    }

    public function getShouldPushDb(): bool
    {
        return $this->shouldPushDb;
    }

    public function getShouldPushArchive(): bool
    {
        return $this->shouldPushArchive;
    }
}
