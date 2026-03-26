<?php

namespace Yashus\WPD\Archive;

use phpseclib3\Net\SSH2;
use RuntimeException;
use Yashus\WPD\Types\YASWPD\EnvSettings;

class ArchiveRemote
{

    private string $wp_dir;
    private string $remote_tmp_dir;
    private string $wpcli;

    public function __construct(array $params)
    {
        $this->wp_dir = $params['wp_dir'];
        $this->remote_tmp_dir = $params['remote_tmp_dir'];
        if (!is_array($params['wpcli'])) {
            throw new \Exception('wpcli command must be an array');
        }
        $this->wpcli = implode(" ", $params['wpcli']);
    }


    /**
     * Create archive
     * TODO: check for previous archive, ask if you want to overwrite
     */
    public function create(SSH2 $ssh, string $archiveFilename, array $exclude = []): bool
    {
        $archiveFilename = escapeshellarg($archiveFilename);
        $remoteTmpDir    = escapeshellarg($this->remote_tmp_dir);
        $tar_cmd = $this->getTarCommand($archiveFilename, $exclude);
        // set a longer timeout for large archives
        $ssh->setTimeout(3600);
        $result = $ssh->exec("
            cd {$this->wp_dir} || exit 1;
            # verify wordpress directory
            if ! {$this->wpcli} db check --quiet &>/dev/null; then
                exit 1;
            fi;
            # create the archive
            echo 'Running tar command: {$tar_cmd}'
            tar_cmd=\$({$tar_cmd})
            # handle errors
            if [[ \$? -ne 0 ]]; then
                echo \"Error archiving wp-content: \$tar_cmd\"
                # remove failed archive if it exists
                if [[ -f {$archiveFilename} ]]; then
                    rm {$archiveFilename}
                fi
                exit 1
            fi
            echo 'Archive Success! dir:{$this->wp_dir} file:{$archiveFilename}'
            echo \"Full report: \$tar_cmd\"
            echo 'Moving archive {$archiveFilename} to tmp directory: {$remoteTmpDir}'
            mv {$archiveFilename} {$remoteTmpDir} || { echo 'Failed to move archive to {$remoteTmpDir}'; exit 1; }
        ");

        $status = $ssh->getExitStatus();
        if ($status != 0) {
            throw new \Exception("Remote archive creation failed with status $status: " . $result);
        }

        return true;
    }

    public function verifyWordpressDirectory(SSH2 $ssh)
    {
        $ssh->exec("cd {$this->wp_dir} || exit 1; }");
        return $ssh->getExitStatus() == 0;
    }

    public function getWPContentDirectorySize(SSH2 $ssh)
    {
        $result = $ssh->exec("
            cd {$this->wp_dir}
            size=$(du -sh wp-content)
            [[ \$? -ne 0 ]] && exit 1
            echo \"\$size\" | awk '{print $1}'
        ");
        if ($ssh->getExitStatus() != 0) {
            throw new \Exception("Failed to get size of wp-content dir: $result");
        }
        return trim($result);
    }

    /**
     * Build the tar command with option to exclude directories
     * @example tar -zcf 'wp-content-archive-2026-03-19.tar.gz' wp-content
     */
    private function getTarCommand(string $archiveFilename, array $exclude)
    {
        // get the excluded directories
        $tarExclude = array_map(function ($dir) {
            return "--exclude='wp-content/$dir'";
        }, $exclude);
        $tarExclude = implode(" ", $tarExclude);
        // build the command array 
        $commands = ['tar', $tarExclude, '-zcf', $archiveFilename, 'wp-content', '2>&1'];
        $cmd = array_filter($commands, function ($cmd) {
            return !empty($cmd);
        });
        $cmd = implode(' ', $cmd);
        return $cmd;
    }

    /**
     * 
     * Unzip
     * 
     * Options
     * --no-overwrite-dir   
     * --no-same-owner
     * --no-same-permissions
     * --x-attrs
     * 
     * For exit codes @see https://www.gnu.org/software/tar/manual/html_node/Synopsis.html
     * 
     * @param SSH2 $ssh 
     * @param string $archiveFilepath 
     * @param string $archiveFilename 
     * @param mixed &$output 
     * @param mixed &$exit_code 
     * @return bool 
     * @throws mixed 
     */
    public static function unzip(SSH2 $ssh, string $archiveFilepath, string $archiveFilename, &$output = null, &$exit_code = null)
    {
        $archiveFilepath = escapeshellarg($archiveFilepath);
        $archiveFilename = escapeshellarg($archiveFilename);

        $output = $ssh->exec("
            cd $archiveFilepath || exit 1
            if [[ ! -f $archiveFilename ]]; then
                echo \"Archive file $archiveFilename does not exist at \${PWD}\"
                exit 1
            fi
            tar --warning=no-unknown-keyword --no-same-permissions -xvzf $archiveFilename
        ");
        $exit_code = $ssh->getExitStatus();
        $msg = "";
        switch ($exit_code) {
            case 2:
                $msg .= "Fatal error.";
                break;
            case 1:
                $msg .= "Some files differ.";

            case 0:
                return true;

            default:
                $msg .= "Error code ($exit_code)";
        }

        throw new RuntimeException("Failed to unzip archive: $msg. " . $output, $exit_code);
    }

    public static function remove(SSH2 $ssh, string $archiveFilepath, string $archiveFilename, &$output = null, &$exit_code = null)
    {
        $archiveFilepath = escapeshellarg($archiveFilepath);
        $archiveFilename = escapeshellarg($archiveFilename);
        $output = $ssh->exec("
         cd $archiveFilepath || exit 1
         rm $archiveFilename || exit 1
        ");
        $exit_code = $ssh->getExitStatus();
        if ($exit_code !== 0) {
            throw new RuntimeException('Failed to remove archive ' . $output, $exit_code);
        }
        return $exit_code === 0;
    }
}
