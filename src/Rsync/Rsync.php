<?php

namespace Yashus\WPD\Rsync;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class Rsync
{


    /**
     * TODO: buffer output / progress 
     * 
     * To get progress run --dry-run of command with --stats option,
     * which will give you number of files etc.
     * 
     * @example
     * Number of files: 1
     * Number of files transferred: 0
     * Total file size: 9764303 B
     * Total transferred file size: 0 B
     * Unmatched data: 0 BMatched data: 0 B
     * File list size: 36 B
     * Total sent: 52 B
     * Total received: 20 B
     * 
     * sent 52 bytes  received 20 bytes  720000 bytes/sec
     * total size is 9764303  speedup is 135613.44
     * 
     * @see https://symfony.com/doc/current/components/console/helpers/progressbar.html
     * 
     * @param mixed $src 
     * @param mixed $dest 
     * @param int $timeout 
     * @param bool $dry_run 
     * @return true 
     * @throws ProcessFailedException 
     */
    public static function run($src, $dest, ?RsyncOptions $options = null, ?OutputInterface $output = null)
    {
        $progressBar = null;
        // init the options
        if (!$options) {
            $options = new RsyncOptions([
                'timeout' => 3600,
                'dry_run' => false
            ]);
        }
        // build the command
        $rsyncCmd = [
            'rsync',
            '-avP',
            "$src",
            "$dest"
        ];

        if ($options->isDryRun()) {
            array_shift($rsyncCmd);
            $rsyncCmd = ['rsync', '--dry-run', ...$rsyncCmd];
        }

        // init the process
        $process = new Process($rsyncCmd);
        $process->setTimeout($options->getTimeout()); // optional: set timeout in seconds (default is 60s)
        if (isset($output)) {
            $progressBar = new ProgressBar($output, $units = 50);
        }
        $process->run(

            // show rsync progress from buffer
            function (string $type, string $buffer) use ($progressBar): void {
                if (isset($progressBar)) {
                    self::handleProgressBarProgress($type, $buffer);
                } else {
                    if (Process::ERR === $type) {
                        echo 'ERR: ' . $buffer;
                    } else {
                        echo $buffer;
                    }
                }
            }
        );


        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        echo ($process->getOutput());
        return true;
    }

    public static function handleProgressBarProgress(string $type, string $buffer) {}
}
