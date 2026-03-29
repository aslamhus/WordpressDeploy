<?php


namespace Yashus\WPD\Types\YASWPD;

use Yashus\WPD\Env\Env;

class ComposerConfig extends AbstractArraySettings
{


    public Env $env;


    public function __construct(array $params, Env $env)
    {
        $this->data['json'] = $params['json'] ?? "";
        if (isset($params['cmd']) && !is_array($params['cmd'])) {
            throw new \Exception("Composer cmd must be an array. Check your composer settings for $env in your .yaswpd.json config");
        }
        $this->data['cmd'] = $params['cmd'] ?? ['composer'];
        if (isset($params['install'])) {
            $this->data['install'] = $params['install'];
        }
    }

    public function getComposerCmd(): string
    {
        return implode(" ", $this->data['cmd']);
    }

    public function getComposerInstallOptions(): ?string
    {
        return implode(" ", $this->data['install']);
    }
}
