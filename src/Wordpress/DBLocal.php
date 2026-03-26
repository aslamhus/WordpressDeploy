<?php

namespace Yashus\WPD\Wordpress;

use Yashus\WPD\Docker\DockerUtils;
use Yashus\WPD\Process\Process;
use Yashus\WPD\Types\YASWPD\EnvObject;
use Yashus\WPD\Types\YASWPD\EnvSettings;

class DBLocal extends AbstractDB
{



    public function import($filename): bool
    {
        return Process::run([
            ...$this->wpcli,
            'db',
            "import",
            "$filename"
        ], $this->wp_dir);
    }



    public  function export(string $filename = "", &$output = null, &$exit_code = null): bool
    {

        return Process::run([
            ...$this->wpcli,
            'db',
            "export",
            "$filename"
        ], $this->wp_dir, $output, $exit_code);
    }

    public  function searchReplace(string $search, $replace, &$output = null): bool
    {


        return  Process::run([
            ...$this->wpcli,
            'search-replace',
            '--all-tables',
            '--recurse-objects',
            "$search",
            "$replace"
        ], $this->wp_dir, $output);
    }

    /**
     * @param EnvObject $fromEnv - local, production, or staging settings
     * @param EnvObject $toEnv - local, production, or staging settings
     */
    public function searchReplaceLocalDbUrl(EnvSettings $fromEnv, EnvSettings $toEnv)
    {
        $search = $fromEnv['url'];
        $replace = $toEnv['url'];
        // regular search replace on raw urls
        $this->searchReplace($search, $replace);
        // escape slashes to search replace JSON encoded urls
        $this->searchReplace(json_encode($search), json_encode($replace));
    }
}
