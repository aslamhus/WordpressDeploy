<?php

namespace Yashus\WPD\Types\YASWPD;


class WordpressSettings extends AbstractArraySettings
{

    public function __construct(array $params)
    {
        $this->data['user'] = $params['user'] ?? 'admin';
        $this->data['email'] = $params['email'] ?? 'admin@example.com';
    }
}
