<?php

namespace Yashus\WPD\Types\YASWPD;

class WPCLISettings extends AbstractArraySettings
{

    public function __construct(array $params)
    {
        if (!is_array($params['remote'])) {
            throw new \Exception("Wpcli remote command must be an array");
        }
        if (!is_array($params['local'])) {
            throw new \Exception("Wpcli local command must be an array");
        }

        $this->data['remote'] = $params['remote'] ?? ["wp"];
        $this->data['local'] = $params['local'] ?? ["vendor/bin/whr", "wp"];
    }
}
