<?php

namespace Yashus\WPD\Archive;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Yashus\WPD\Process\Process;
use Yashus\WPD\Utils\Utils;


/**
 * TODO: get tar type
 * @package Yashus\WPD\Archive
 */
class ArchiveLocal
{

    private string $src;
    private string $filename;
    private string $archiveFilepath;
    private array|string $exclude;
    private string $tarType = 'bsdtar';
    public string $tarCmd;

    public function __construct(string $src, string $filename, array|string $exclude = [])
    {
        $this->src = $src ?? throw new \Exception('Archive src cannot be empty');
        $this->filename = $filename ?? throw new \Exception('Archive filename cannot be empty');
        // remove any file extension
        if (preg_match("/\.(tar|gz|zip|gzip)$/", $this->filename)) {
            $this->filename = $this->removeExt($this->filename);
        }
        $this->filename = $this->getArchiveFilename();
        $this->archiveFilepath = getcwd();
        $this->exclude = $exclude ?? [];
        // if a file is supplied, convert file contents into an array
        if (!is_array($this->exclude) && gettype($this->exclude) == 'string') {
            $this->exclude = Utils::getExcludesFromFile($exclude);
        }
        $this->tarType = $this->getTarType();
    }

    public function previousArchiveExists(): bool
    {
        return file_exists($this->filename);
    }

    public function verifyIntegrity(string $filename): bool
    {
        $filename = escapeshellarg($filename);
        return Process::fromShellCommandLine("tar xOf $filename &> /dev/null");
    }

    public function getArchiveFilepath(): string
    {
        return $this->archiveFilepath . DIRECTORY_SEPARATOR . $this->filename;
    }

    public function getTarCmd(): string
    {
        if (!isset($this->tarCmd)) {
            throw new \Exception("tar command has not run yet");
        }
        return $this->tarCmd;
    }



    /**
     * Archive a directory
     * This functions cds into the src directory and creates an archive with ./
     * All excludes are relative to this path
     * 
     * COPYFILE_DISABLE=1   prevents ._ files (OS X-specific extended attributes)
     * --no-x-attrs         ibid
     * @param mixed &$output 
     * @param mixed &$exit_code 
     * @return false|string 
     * @throws ProcessFailedException
     */
    public function create(&$output = null, &$exit_code = null): false|string
    {


        $archiveFilename = $this->filename;
        $tarCmd = ['tar',   ...$this->getExcludes(),   '--no-xattrs', '-czf', '../' . $archiveFilename, "./",];
        $this->tarCmd = implode(' ', $tarCmd);
        $p =  Process::run($tarCmd, $this->src, $output, $exit_code, false, ['COPYFILE_DISABLE' => 1]);

        return getcwd() . DIRECTORY_SEPARATOR . $archiveFilename;
    }



    /**
     * Returns array of exclude options: [--exclude='file1', --exclude='file2', ...]
     * @return array 
     */
    private function getExcludes(): array
    {
        return array_map(function ($exclude) {
            // replace leading slash with ^ for bsdtar and ./ for gtar
            $replace = "^";
            switch ($this->tarType) {
                case 'bsdtar':
                    $replace =  '^';
                    break;
                case 'gtar':
                    $replace = './';
                    break;
            }

            $firstChar = substr($exclude, 0, 1);

            if ($firstChar == '/') {
                $exclude = substr_replace($exclude, $replace, 0, 1);
            }
            return "--exclude=$exclude";
        }, $this->exclude) ?? [];
    }



    private function removeExt(string $filename): string
    {
        return preg_replace("/\.(tar|gz|zip|gzip)/", "", $filename);
    }

    private function getArchiveFilename(): string
    {
        return "{$this->filename}.tar.gz";
    }

    /**
     * Unzip archive in place
     * 
     * @param string $filename 
     * @param mixed $cwd 
     * @param mixed &$output 
     * @param mixed &$exit_code 
     * @return bool 
     */
    public static function unzip(string $filename,  $cwd = null, &$output = null, &$exit_code = null): bool
    {
        $unzipCmd = ['tar', '-xzf', $filename];
        return Process::run($unzipCmd, $cwd, $output, $exit_code);
    }

    private function getTarType()
    {
        $isBsdTar =  Process::fromShellCommandLine('tar --version | grep bsdtar');
        return $isBsdTar ? 'bsdtar' : 'gnutar';
    }
}
