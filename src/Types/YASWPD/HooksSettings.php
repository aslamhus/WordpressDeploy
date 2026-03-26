<?php

namespace Yashus\WPD\Types\YASWPD;


class HooksSettings extends AbstractArraySettings
{


    public const hooks = [
        'prePush',
        'postPush'
    ];
    public function __construct($params)
    {

        foreach (self::hooks as $hook) {
            if (isset($params[$hook])) {
                $this->data[$hook] = $params[$hook];
            }
        }
    }
}
