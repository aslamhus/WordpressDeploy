<?php

namespace Yashus\WPD\Wordpress;

use phpseclib3\Net\SSH2;

class DBRemote extends AbstractDB
{


    public function import(SSH2 $ssh, string $filepath)
    {
        $wpcli = $this->getWpCliCommand_asString();
        $filepath = escapeshellarg($filepath);
        $result = $ssh->exec("
            cd {$this->wp_dir} || exit 
            if ! -f $filepath; then
                echo \"File $filepath does not exist at \$PWD\"
            fi
            $wpcli db import $filepath
        ");
        $exit_code = $ssh->getExitStatus();
        if ($exit_code != 0) {
            throw new \RuntimeException("Remote DB import failed with status $exit_code: " . $result, $exit_code);
        }
    }

    public  function export(SSH2 $ssh,  string $filepath)
    {
        $wpcli = $this->getWpCliCommand_asString();
        $result =  $ssh->exec("cd {$this->wp_dir} && $wpcli db export $filepath");
        $exit_code = $ssh->getExitStatus();
        if ($exit_code != 0) {
            throw new \RuntimeException("Remote DB export failed with status $exit_code: " . $result, $exit_code);
        }
        return true;
    }

    public static function remove($ssh, $filepath): bool
    {
        $filepath = escapeshellarg($filepath);
        $result = $ssh->exec("
            if [[ ! -f '{$filepath}' ]]; then 
                echo 'not a file {$filepath}'
                exit 1
            else
                rm {$filepath} || exit 1
            fi
        ");
        $exit_code = $ssh->getExitStatus();
        if ($exit_code != 0) {
            throw new \RuntimeException("Removing remote database failed at $filepath: $result", $exit_code);
        }
        return true;
    }
}
