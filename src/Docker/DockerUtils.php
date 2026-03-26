<?php

namespace Yashus\WPD\Docker;

use Yashus\WPD\Process\Process;

class DockerUtils
{

    /**
     * Check for running container with working dir basename
     * @return bool 
     */
    public static function verifyContainerIsRunning(): bool
    {

        $working_dir_name = escapeshellarg(basename(getcwd()));
        $docker_container_name = DockerUtils::getDockerWorkingDirName($working_dir_name);

        try {
            Process::fromShellCommandLine("docker container ls | grep -q $docker_container_name", $output, $exit_code);
        } catch (\Exception $e) {
            throw new \Exception('Docker container ' . $docker_container_name . ' is not running. Please start your container');
        }

        return true;
    }

    /**
     * Note: docker container name restricted to alphanumeric and hyphens
     * Restricted characters @see https://github.com/moby/moby/blob/be97c66708c24727836a22247319ff2943d91a03/daemon/names/names.go
     * @param string $working_dir_name 
     * @return string|string[]|null 
     */
    public static function getDockerWorkingDirName(string $working_dir_name)
    {
        return preg_replace("/[^a-zA-Z0-9-]/", '', $working_dir_name);
    }

    public static function verifyDockerIsRunning(): bool
    {
        try {
            $p = Process::fromShellCommandLine('! docker info >/dev/null 2>&1', $output, $exit_code);
        } catch (\Exception $e) {

            return false;
        }

        return true;
    }
}
