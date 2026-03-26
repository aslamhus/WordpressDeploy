<?php

namespace Yashus\WPD\Main\Push;

use Yashus\WPD\Env\Env;
use Yashus\WPD\Types\YASWPD\EnvSettings;
use Yashus\WPD\Types\YASWPD\Settings;
use Yashus\WPD\Wordpress\DBLocal;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yashus\WPD\Types\Push\PushOptions;
use Yashus\WPD\Main\AbstractMain;
use Yashus\WPD\Process\Process;

abstract class AbstractHook extends AbstractMain
{

    protected Env $env;
    protected DBLocal $dbLocal;
    protected Settings $settings;
    protected EnvSettings $remote;
    protected PushOptions $options;
    protected string $dbExportFilename;

    public function __construct(Env $env, DBLocal $dbLocal, Settings $settings, EnvSettings $remote, PushOptions $options, OutputInterface $output, InputInterface $input, SymfonyStyle $io, string $dbExportFilename)
    {
        parent::__construct($input, $output, $io);
        $this->env = $env;
        $this->dbLocal = $dbLocal;
        $this->settings = $settings;
        $this->remote = $remote;
        $this->options = $options;
        $this->output = $output;
        $this->dbExportFilename = $dbExportFilename;
    }


    protected function runHookScripts(string $hookName = Hooks::prePush): void
    {
        if (!isset($this->settings->hooks[$hookName])) return;
        $scripts = $this->settings?->hooks[$hookName];
        if (!empty($scripts)) {
            $this->output->writeln("<comment>Running $hookName scripts</comment>");
            foreach ($scripts as $script) {
                $scriptPath = getcwd() . DIRECTORY_SEPARATOR . ltrim($script, './');
                $this->output->writeln("Running $script");
                try {
                    Process::run([$scriptPath], null, $output, $exit_code, false, ['settings' => json_encode($this->settings)]);
                } catch (\Exception $e) {
                    throw new \RuntimeException("Error running $hookName script '$script'. \nExit code: $exit_code. \nOutput: $output", $exit_code);
                }
                $this->output->write("\n" . $output . "\n");
                $this->output->writeln("✔️ Executed $script");
            }
        }
    }
}
