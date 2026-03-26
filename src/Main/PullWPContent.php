<?php

namespace Yashus\WPD\Main;

use phpseclib3\Net\SSH2;
use Symfony\Component\Console\Input\InputInterface;
use Yashus\WPD\SSH\SSH;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yashus\WPD\Console\Console;
use Yashus\WPD\Process\Process;
use Yashus\WPD\Rsync\Rsync;
use Yashus\WPD\Types\YASWPD\EnvSettings;
use Yashus\WPD\Types\YASWPD\Settings;
use Yashus\WPD\Utils\Utils;
use Yashus\WPD\Archive\ArchiveRemote;
use Yashus\WPD\Env\Env;

class PullWPContent extends AbstractMain
{

    private SSH $ssh;
    private Env $env;
    private EnvSettings $remote;
    private Settings $settings;
    private string $archive_name;
    private array $exclude = [];

    public function __construct(SSH $ssh, Settings $settings, array $exclude, InputInterface $input,  OutputInterface $output, SymfonyStyle $io,)
    {
        parent::__construct($input, $output, $io);
        $this->ssh = $ssh;
        $this->settings = $settings;
        $this->env = new Env('production'); // Could pull from staging if we wanted to if there is enough interest/need
        $this->remote = $settings->getEnvSettings($this->env);
        $this->archive_name = $this->getArchiveName();
        $this->exclude = $exclude ?? [];
        $this->run();
    }

    public function run(): void
    {

        if (Console::confirm('Would you like to download wp-content?')) {
            $ssh = $this->ssh->connect();
            // TODO: check for current archive in destination folder
            if ($this->archiveAlreadyExists()) {
                if (Console::confirm('An archive with name ' . $this->archive_name . ' already exists. Would you like to overwrite it?')) {
                    $this->removeLocalArchive();
                    $this->archiveAndDownload($ssh);
                }
            } else {

                $this->archiveAndDownload($ssh);
            }
            // TODO: backup local
            if (Console::confirm('Would you like to extract the archive?')) {
                if (!$this->extractArchive()) {
                    throw new \Exception("Failed to extact archive");
                }
                $this->output->writeln('<fg=green>✅ wp-content extracted!</>');
            }
        }
    }

    private function archiveAndDownload($ssh)
    {
        if (!$this->archiveRemoteWpContent($ssh)) {
            throw new \Exception('Failed to archive remote wp content');
        }
        if (!$this->downloadArchive($ssh)) {
            throw new \Exception('Downloading archive failed');
        }
    }
    private function archiveRemoteWpContent(SSH2 $ssh)
    {
        $archive = new ArchiveRemote([
            'wp_dir' => $this->remote->getPublicPath(),
            'remote_tmp_dir' => $this->remote['tmp'],
            'wpcli' => $this->settings->wpcli['remote']
        ]);
        if ($archive->verifyWordpressDirectory($ssh)) {
            throw new \Exception("Failed to create archive, not a valid wordpress directory");
        }
        $dirSize = $archive->getWPContentDirectorySize($ssh);
        if ($this->confirm("Are you sure you want to archive and download the wp-content folder ($dirSize)?")) {
            $this->output->writeln('<comment>Archiving wp-content</comment>');
            return $archive->create($ssh, $this->archive_name, $this->exclude);
        }
        return false;
    }

    private function downloadArchive()
    {
        $this->output->writeln('<comment>Downloading archive</comment>');
        $config = $this->settings->ssh->getEnvConfig($this->env);
        $user = $config['user'];
        $host = $config['host'];
        $src = "$user@$host:" . $this->remote['tmp'] . DIRECTORY_SEPARATOR . $this->archive_name;
        $dest = $this->settings->local->getPublicPath();
        return Rsync::run($src, $dest);
    }

    private function extractArchive()
    {
        $this->output->writeln('<comment>Extracting archive</comment>');
        $wp_dir = $this->getWpDir();
        if (!file_exists($wp_dir . DIRECTORY_SEPARATOR . $this->archive_name)) {
            throw new \Exception('Archive file does not exist');
        }
        // extract
        return Process::run(
            ['tar',  '-xvzf',  $this->archive_name],
            $wp_dir
        );
    }

    private function removeLocalArchive()
    {
        // TODO: ask if they want to remove archive
        return Process::run(['rm', $this->archive_name], $this->getWpDir());
    }

    private function getWpDir()
    {
        return  $this->settings->local['root'] . DIRECTORY_SEPARATOR . $this->settings->local['public'];
    }

    private function archiveAlreadyExists(): bool
    {
        return file_exists($this->settings->local['root'] .
            DIRECTORY_SEPARATOR .
            $this->settings->local['public'] .
            DIRECTORY_SEPARATOR .
            $this->archive_name);
    }

    private function getArchiveName()
    {
        return "wp-content-archive-" . Utils::getTodayFormatted() . ".tar.gz";
    }
}
