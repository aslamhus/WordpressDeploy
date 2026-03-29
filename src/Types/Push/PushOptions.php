<?php

namespace Yashus\WPD\Types\Push;

use JsonSerializable;
use Yashus\WPD\Types\YASWPD\AbstractArraySettings;

class PushOptions implements JsonSerializable
{

    public bool $shouldPushDb;
    public bool $shouldPushArchive;
    public bool $shouldPushComposer;
    public bool $shouldFlushCache;
    public bool $interaction;
    private array $data;

    public function __construct(array $params, bool $isInteractive)
    {
        $this->shouldPushDb = $params['shouldPushDb'] ?? false;
        $this->shouldPushArchive = $params['shouldPushArchive'] ?? false;
        $this->shouldPushComposer = $params['shouldPushComposer'] ?? false;
        $this->shouldFlushCache = $params['shouldFlushCache'] ?? false;
        $this->interaction = $params['interaction'] ?? false;
        // push all set properties to data array for json serialization
        foreach (get_class_vars(self::class) as $key => $value) {
            if (!isset($this->{$key}) || $key == 'data') continue;
            $this->data[$key] = $this->{$key};
        }
    }

    public function setShouldPushDb(bool $value)
    {
        $this->shouldPushDb = $value;
    }

    public function setShouldPushArchive(bool $value)
    {
        $this->shouldPushArchive = $value;
    }

    public function setShouldPushComposer(bool $value)
    {
        $this->shouldPushComposer = $value;
    }

    public function setShouldFlushCache(bool $value)
    {
        $this->shouldFlushCache = $value;
    }
    public function setInteraction(bool $interaction)
    {
        $this->interaction = $interaction;
    }



    public function getShouldPushDb(): bool
    {
        return $this->shouldPushDb;
    }


    public function getShouldPushArchive(): bool
    {
        return $this->shouldPushArchive;
    }
    public function getShouldPushComposer(): bool
    {
        return $this->shouldPushComposer;
    }

    public function getShouldFlushCache(): bool
    {
        return $this->shouldFlushCache;
    }

    public function getInteraction(): bool
    {
        return $this->interaction;
    }

    public function jsonSerialize(): mixed
    {
        foreach (get_class_vars(self::class) as $key => $value) {
            if (!isset($this->{$key}) || $key == 'data') continue;
            $this->data[$key] = $this->{$key};
        }
        return $this->data;
    }
}
