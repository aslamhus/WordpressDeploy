<?php

namespace Yashus\WPD\Wordpress;

abstract class AbstractDB
{

    protected array $wpcli;
    protected string $wp_dir;

    /**
     * @param Array $params - ['wpcli' => [], 'wp_dir' => 'path/to/public/wp/dir' ]
     */
    public function __construct(array $params)
    {
        $this->wpcli = $params['wpcli'];
        $this->wp_dir = $params['wp_dir'];
        if (!in_array('wp', $this->wpcli)) {
            throw new \Exception('wpcli command invalid. It must include "wp". Instead found: ' . $this->getWpCliCommand_asString());
        }
    }

    public function getWpCliCommand_asString(): string
    {
        return implode(" ", $this->wpcli);
    }
}
