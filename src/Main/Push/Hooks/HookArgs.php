<?php


namespace Yashus\WPD\Main\Push\Hooks;

use Yashus\WPD\Types\YASWPD\AbstractArraySettings;
use Yashus\WPD\Env\Env;
use Yashus\WPD\Wordpress\DBLocal;
use Yashus\WPD\Types\YASWPD\Settings;
use Yashus\WPD\Types\YASWPD\EnvSettings;
use Yashus\WPD\Types\Push\PushOptions;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class HookArgs extends AbstractArraySettings
{

    public Env $env;
    public DBLocal $dbLocal;
    public Settings $settings;
    public PushOptions $options;
    public EnvSettings $remote;
    public OutputInterface $output;
    public InputInterface $input;
    public SymfonyStyle $io;
    public string $dbExportFilename;
    public string $archiveFilename;
    public string $deployIgnore;

    public function __construct(Env $env, Settings $settings, EnvSettings $remote, PushOptions $options, OutputInterface $output, InputInterface $input, SymfonyStyle $io, string $dbExportFilename, string $archiveFilename, string $deployIgnore)
    {
        $this->env = $env;
        $this->settings = $settings;
        $this->remote = $remote;
        $this->options = $options;
        $this->output = $output;
        $this->input = $input;
        $this->io = $io;
        $this->dbExportFilename = $dbExportFilename;
        $this->archiveFilename = $archiveFilename;
        $this->deployIgnore = $deployIgnore;
    }
}
