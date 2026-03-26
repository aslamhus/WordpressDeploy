<?php

namespace Yashus\WPD\Process;

use Symfony\Component\Process\Exception\ProcessFailedException;

class Process
{


    /**
     * From shell command line
     * @throws ProcessFailedException - when exit code !== 0
     * @return bool - true when exit code === 0
     */
    public static function fromShellCommandLine(mixed $commandLine, &$output = null, &$exit_code = null, $mustRun = false)
    {
        $process = \Symfony\Component\Process\Process::fromShellCommandLine($commandLine);
        return self::evaluateProcess($process, $output, $exit_code, $mustRun);
    }

    /**
     * Run process
     * 
     * @throws ProcessFailedException - when exit code !== 0
     * @return bool - true when exit code === 0
     */
    public static function run(array $command, $cwd = null, &$output = null, &$exit_code = null, $mustRun = false, ?array $env = null): bool
    {
        $process = new \Symfony\Component\Process\Process($command, $cwd, $env);
        return self::evaluateProcess($process, $output, $exit_code, $mustRun);
    }


    private static function evaluateProcess(\Symfony\Component\Process\Process $process, &$output = null, &$exit_code = null, $mustRun = false)
    {

        $exit_code = $process->run();
        $output = $process->getOutput();
        // verifies if exit_code === 0
        if (!$process->isSuccessful()) {
            $output .= $process->getErrorOutput();
            throw new ProcessFailedException($process);
        }

        return true;
    }
}
