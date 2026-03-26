<?php

namespace Yashus\WPD\Main;

use COM;
use DateTime;
use DateTimeZone;
use Symfony\Component\Console\Input\InputInterface;
use Yashus\WPD\SSH\SSH;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yashus\WPD\Console\Console;
use Yashus\WPD\Docker\DockerUtils;
use Yashus\WPD\Env\Env;
use Yashus\WPD\Rsync\Rsync;
use Yashus\WPD\Types\YASWPD\DockerSettings;
use Yashus\WPD\Types\YASWPD\EnvSettings;
use Yashus\WPD\Types\YASWPD\Settings;
use Yashus\WPD\Utils\Utils;
use Yashus\WPD\Wordpress;
use Yashus\WPD\Wordpress\DBLocal;
use Yashus\WPD\Wordpress\DBRemote;

class PullDB extends AbstractMain
{

    private SSH $ssh;
    private Env $env;
    private EnvSettings $remote;
    private EnvSettings $local;
    private Settings $settings;
    private DBLocal $dbLocal;
    private DBRemote $dbRemote;
    private string $filename;
    private string $remote_tmp_file;
    private string $local_db_destination;

    public function __construct(SSH $ssh, Settings $settings, InputInterface $input,  OutputInterface $output, SymfonyStyle $io)
    {
        parent::__construct($input, $output, $io);
        $this->ssh = $ssh;
        $this->local = $settings->local;
        $this->settings = $settings;
        $this->env = new Env('production'); // Could pull from staging if we wanted to if there is enough interest/need
        $this->remote = $settings->getEnvSettings($this->env);

        $this->filename = $this->getFilename($this->env->__toString());
        $this->remote_tmp_file = $this->remote['tmp'] . DIRECTORY_SEPARATOR .  $this->filename;
        // by default, we use the the project root as the destination for the database file
        $this->local_db_destination = $this->getDBDestinationPath();

        $this->dbRemote = new DBRemote(([
            'wpcli' => $settings['wpcli']['remote'],
            'wp_dir' => $this->remote['root'] . DIRECTORY_SEPARATOR . $this->remote['public']
        ]));
        $local_wp_dir = $this->local['root'] . DIRECTORY_SEPARATOR . $this->local['public'];
        // when using docker, the wpcli container automatically sets the working directory as the wordpress public dir
        if ($this->settings->docker->isUsingDocker()) {
            $local_wp_dir = "./";
        }
        $this->dbLocal = new DBLocal([
            'wpcli' => $settings['wpcli']['local'],
            'wp_dir' => $local_wp_dir
        ]);


        $this->run();
    }

    public function run()
    {

        if (Console::confirm('Would you like to download the database?')) {
            $this->downloadDB();
            if (Console::confirm("Would you like to import {$this->filename} to your local site's database?")) {
                $this->importDBToLocal();
            }
        }
    }

    private function downloadDB()
    {
        // check for existing database at destination
        if (file_exists($this->local_db_destination)) {
            $this->output->writeln("A database with the filename " . $this->filename . " already exists");
            if (!Console::confirm('Would you like to download a new one?')) {
                // skip if user doesn't want to download new db
                return;
            }
            // TODO: update filename and start process again
            // for instance if production-2025-05-05.sql exists, append -1 or -2 depending on previous filename
        }
        // run commands
        $ssh = $this->ssh->connect();
        // export remote db
        $sshConfig =  $this->settings->ssh->getEnvConfig($this->env);
        $this->output->writeln("<comment>Exporting remote db on " . $sshConfig['host'] . "</comment>");
        $this->exportRemoteDb($ssh);
        // download it to local
        $this->output->writeln("<comment>Downloading db to local " . $this->local_db_destination . "</comment>");
        $this->downloadDBToLocal();

        // remove db export
        $this->output->writeln("Removing exported database on remote");
        $this->removeRemoteDBExport($ssh);
        // success message
        $this->output->writeln(["<fg=green>✔️ Database downloaded to {$this->local_db_destination}!</>", ""]);
        // end the ssh session
        $ssh->disconnect();
        return;
    }

    private function exportRemoteDb($ssh): bool
    {
        return $this->dbRemote->export($ssh, $this->remote_tmp_file);
    }

    private function removeRemoteDBExport($ssh): bool
    {
        return Wordpress\DBRemote::remove($ssh,  $this->remote_tmp_file);
    }


    private function downloadDBToLocal(): bool
    {
        $this->output->writeln('<comment>Downloading archive</comment>');
        $config = $this->settings->ssh->getEnvConfig($this->env);
        $user = $config['user'];
        $host = $config['host'];
        $dest = $this->local_db_destination;
        $src = "$user@$host:" . $this->remote_tmp_file;
        // rsync db export to local destination
        return Rsync::run($src, $dest);
    }

    private function importDBToLocal()
    {
        // back up first
        // the path is either tmp dir inside the docker container or the project root
        $path = Utils::getLocalWPDBImportExportPath($this->settings->docker);
        $this->dbLocal->export($path . DIRECTORY_SEPARATOR . $this->getFilename('backup-local'));
        // import db 
        $this->dbLocal->import($path . DIRECTORY_SEPARATOR . $this->filename);
        // search replace db
        $this->searchReplaceLocalDb();
    }

    private function getFilename(string $prefix = '', string $suffix = '')
    {
        return "$prefix-" . Utils::getTodayFormatted() . $suffix . ".sql";
    }

    private function getDBDestinationPath()
    {
        $path = $this->local['root'];
        // when using docker, we should download to the local path which serves as an entrypoint to the container
        // this allows us to import the database into the container
        if ($this->settings->docker->isUsingDocker()) {
            $path = $this->settings->docker->getTmpDirMount();
        }
        if (!is_dir($path)) {
            throw new \Exception('Local db file path does not exist: ' . $path);
        }
        return $path  . DIRECTORY_SEPARATOR . $this->filename;
    }








    private function searchReplaceLocalDb()
    {
        $search = $this->remote['url'];
        $replace = $this->local['url'];
        $this->output->writeln("DB Search replace ($search) -> ($replace)");
        $this->dbLocal->searchReplaceLocalDbUrl($this->remote, $this->local);
    }
}
