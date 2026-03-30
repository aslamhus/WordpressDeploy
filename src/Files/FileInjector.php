<?php

namespace Yashus\WPD\Files;

use Symfony\Component\Console\Output\OutputInterface;
use Yashus\WPD\Env\Env;
use Yashus\WPD\Process\Process;
use Yashus\WPD\Types\YASWPD\FileSettings;
use Yashus\WPD\Types\YASWPD\Settings;

class FileInjector
{

    private Settings $settings;
    private OutputInterface $output;

    public function __construct(Settings $settings, OutputInterface $output)
    {
        $this->settings = $settings;
        $this->output = $output;
    }



    public function run(Env $env)
    {
        $this->output->writeln("<comment>🗂️ Injecting env files -> $env</comment>");
        foreach ($this->settings->files as $file) {
            /** @var FileSettings $file */
            if ($this->inject($file, $env)) {
                $this->output->writeln('✔️ Injected env file ' . $file->getEnvFilename($env) . ' ➡ ' . $file->getFileToReplace());
            }
        }
    }

    /**
     * Inject env file content
     * If a file does not exist for an environment, it will simply return
     * If a file is specified and can't be found, an exception is thrown.
     * 
     * @param FileSettings $file 
     * @param Env $env 
     * @return string|mixed 
     * @throws FileNotFoundException 
     */
    private function inject(FileSettings $file, Env $env)
    {
        // directory to search (defaults to public path)
        $search_dir = escapeshellarg(
            $file->getDirectory() ?:
                $this->settings->local->getPublicPath()
        );
        $env_file_name = $file->getEnvFilename($env);
        // skip if no env file is specififed
        if (empty($env_file_name)) return false;
        $target_file_name = escapeshellarg($file->getFileToReplace());
        // find the target file (that will have env file injected into it)
        $target_file = $this->findTargetFile($search_dir, $target_file_name, $env_file_name);
        if (empty($target_file)) {
            throw new FileNotFoundException('Env file not found: ' . $env_file_name . '. Please make sure that the injecting files exist in the same directory');
        }
        // inject the env file content into the target file
        // (we know that both files exist in the same directory)
        $dir = dirname($target_file);
        $inject = file_get_contents($dir . DIRECTORY_SEPARATOR . $env_file_name);
        file_put_contents($target_file, $inject);
        return true;
    }

    private function findTargetFile(string $search_dir, string $target_file, string $env_file_name): ?string
    {
        $p = Process::fromShellCommandLine("find $search_dir -type f -name $target_file", $output);
        // convert output from string to array
        $files = explode(PHP_EOL, $output ?? "");
        $files = array_filter($files, 'strlen');
        if (!$p) {
            // warn user
            throw new FileNotFoundException('Target file not found: ' . $target_file . '. Please make sure that the injecting files exist in the same directory');
        }
        // for each file found, check if the env file exists in teh same directory.
        $found = null;
        foreach ($files as $file) {
            $dir = dirname($file);
            if (file_exists($dir . DIRECTORY_SEPARATOR . $env_file_name)) {
                $found =  $file;
                break;
            }
        }
        return $found;
    }
}


class FileNotFoundException extends \Exception
{
    public function __construct($message, $code = 0, ?\Throwable $previous = null)
    {

        parent::__construct($message, $code, $previous);
    }
}
