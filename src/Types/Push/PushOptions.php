<?php

namespace Yashus\WPD\Types\Push;

use JsonSerializable;

class PushOptions implements JsonSerializable
{

    public bool $pushDb;
    public bool $pushArchive;
    public bool $pushComposer;
    public bool $flushCache;
    public bool $prePushHooks;
    public bool $postPushHooks;
    public bool $interactive;
    private array $data;

    public function __construct(array $params, bool $isInteractive)
    {
        // if one parameter is set to false, (i.e. --no-db, set all default values to true)
        $this->setDefaultPropertyValues($params, $isInteractive);
        // set the parameters
        foreach ($params as $key => $value) {
            if (!is_null($value)) $this->{$key} = $value;
        }
    }

    /**
     * Set the default property values depending on arguments
     * if using negatable arguments such as '--no-db' then default all properties to true
     * otherwise default all to false
     * @return void 
     */
    private function setDefaultPropertyValues(array $params, bool $isInteractive)
    {
        // if no push options have been set, assume all are true
        $this->interactive = $isInteractive;
        $noParamsAreSet = $this->noParamsAreSet($params);
        $default = $this->hasNegatableValue($params) || $noParamsAreSet ? true : false;
        foreach (get_class_vars(self::class) as $key => $value) {
            if ($key === 'data' || $key === 'interactive') continue;
            $this->{$key} = $default;
        }
        // disable interaction when options have been set
        if (!$noParamsAreSet) {
            $this->interactive = false;
        }
    }

    private function hasNegatableValue($params): bool
    {
        return !empty(array_filter($params, function ($value) {
            return $value === false;
        }));
    }


    private function noParamsAreSet($params)
    {
        return empty(array_filter($params, function ($p) {

            return isset($p);
        }));
    }

    public function setPushDb(bool $value)
    {
        $this->pushDb = $value;
    }

    public function setPushArchive(bool $value)
    {
        $this->pushArchive = $value;
    }

    public function setPushComposer(bool $value)
    {
        $this->pushComposer = $value;
    }

    public function setflushCache(bool $value)
    {
        $this->flushCache = $value;
    }
    public function setInteraction(bool $interactive)
    {
        $this->interactive = $interactive;
    }



    public function getpushDb(): bool
    {
        return $this->pushDb;
    }


    public function getpushArchive(): bool
    {
        return $this->pushArchive;
    }
    public function getpushComposer(): bool
    {
        return $this->pushComposer;
    }

    public function getFlushCache(): bool
    {
        return $this->flushCache;
    }

    public function getInteractive(): bool
    {
        return $this->interactive;
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
