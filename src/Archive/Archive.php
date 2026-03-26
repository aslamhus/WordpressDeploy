<?php

namespace Yashus\WPD\Archive;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Yashus\WPD\Process\Process;

class Archive
{

    private string $src;
    private string $filename;
    private array|string $exclude;

    public function __construct(string $src, string $filename, array|string $exclude = [])
    {
        $this->src = $src ?? throw new \Exception('Archive src cannot be empty');
        $this->filename = $filename ?? throw new \Exception('Archive filename cannot be empty');
        // remove any file extension
        if (preg_match("/\.(tar|gz|zip|gzip)$/", $this->filename)) {
            $this->filename = $this->removeExt($this->filename);
        }
        $this->exclude = $exclude ?? [];
        // if a file is supplied, convert file contents into an array
        if (!is_array($this->exclude) && gettype($this->exclude) == 'string') {
            $this->exclude = $this->getExcludesFromFile();
        }
    }

    /**
     * Archive a directory
     * This functions cds into the src directory and creates an archive with ./
     * All excludes are relative to this path
     * @param mixed &$output 
     * @param mixed &$exit_code 
     * @return bool
     * @throws ProcessFailedException
     */
    public function create(&$output = null, &$exit_code = null): bool
    {

        $archiveFilename = "{$this->filename}.tar.gz";
        // Note: different tar versions require excludes to go before the other flags or after the filename
        $tarCmd = ['tar',   ...$this->getExcludes(),   '-cz',  '-f', $archiveFilename, "./",];
        $p =  Process::run($tarCmd, $this->src, $output, $exit_code);

        if ($p) {
            // move archive to working directory out of src
            Process::run(['mv', $archiveFilename, getcwd()], $this->src);
        }
        return true;
    }

    /**
     * Returns array of exclude options: [--exclude='file1', --exclude='file2', ...]
     * @return array 
     */
    private function getExcludes(): array
    {

        return array_map(function ($exclude) {
            return "--exclude=$exclude";
        }, $this->exclude) ?? [];
    }

    private function getExcludesFromFile(): array
    {
        if (!file_exists($this->exclude)) {
            throw new \Exception("Archive exclude file " . $this->exclude . " does not exist");
        }
        return array_filter(explode(PHP_EOL, file_get_contents($this->exclude)), 'strlen');
    }

    private function removeExt(string $filename): string
    {
        return preg_replace("/\.(tar|gz|zip|gzip)/", "", $filename);
    }
}
