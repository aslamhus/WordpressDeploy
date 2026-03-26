<?php

namespace Yashus\WPD\Types\YASWPD;


class ComposerSettings extends AbstractEnvSettings
{

    public function __construct(array $params)
    {
        parent::__construct($params, ComposerConfig::class);
    }
}
