<?php

namespace Yashus\WPD\Wordpress;

use Yashus\WPD\SSH\SSH;

class DBRemote extends AbstractDB
{


    public function import(SSH $ssh, string $filepath)
    {
        $wpcli = $this->getWpCliCommand_asString();
        $filepath = escapeshellarg($filepath);
        try {
            $ssh->exec("
            cd {$this->wp_dir} || exit 
            if ! -f $filepath; then
                echo \"File $filepath does not exist at \$PWD\"
            fi
            $wpcli db import $filepath
        ", null, $exit_code);
        } catch (\Exception $e) {
            throw new \Exception("Remote DB import failed", $exit_code, $e);
        }
        return true;
    }

    public  function export(SSH $ssh,  string $filepath)
    {
        $wpcli = $this->getWpCliCommand_asString();
        try {
            $ssh->exec("cd {$this->wp_dir} && $wpcli db export $filepath", null, $exit_code);
        } catch (\Exception $e) {
            throw new \Exception("Remote DB export failed", $exit_code, $e);
        }
        return true;
    }

    public static function remove(SSH $ssh, $filepath): bool
    {
        $filepath = escapeshellarg($filepath);
        try {
            $ssh->exec("
            if [[ ! -f '{$filepath}' ]]; then 
                echo 'not a file {$filepath}'
                exit 1
            else
                rm {$filepath} || exit 1
            fi
        ", null, $exit_code);
        } catch (\Exception $e) {
            throw new \Exception("Removing remote database failed", $exit_code, $e);
        }

        return true;
    }
}
