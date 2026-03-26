<?php

namespace Yashus\WPD\Types\YASWPD;


class DockerSettings extends AbstractArraySettings
{


    public function __construct(?array $params)
    {

        if (empty($params)) return;
        $this->data = [];
        $this->data['tmp_volume'] = $this->splitDockerTmpVolume($params['tmp_volume']);
    }




    public function isUsingDocker(): bool
    {
        return !empty($this->data['tmp_volume']);
    }
    public  function getTmpDirContainer(): string
    {
        return $this->data['tmp_volume'][1];
        // return $_SERVER['YAS_WPD']['docker']['tmp_volume'][0];
    }

    public  function getTmpDirMount(): string
    {
        return $this->data['tmp_volume'][0];
        // return $_SERVER['YAS_WPD']['docker']['tmp_volume'][1];
    }

    private  function splitDockerTmpVolume(string $tmp_docker_volume): array
    {
        return explode(":", $tmp_docker_volume);
    }
    private  function implodeDockerTmpVolume(array $tmp_docker_volume): string
    {
        return implode(":", $tmp_docker_volume);
    }

    public function jsonSerialize(): mixed
    {
        return [
            ...$this->data,
            'tmp_volume' => $this->implodeDockerTmpVolume($this->data['tmp_volume'])
        ];
    }
}
