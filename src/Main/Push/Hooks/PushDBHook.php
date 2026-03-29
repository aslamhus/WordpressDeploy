<?php


namespace Yashus\WPD\Main\Push\Hooks;

use Yashus\WPD\Console\Console;
use Yashus\WPD\Rsync\Rsync;
use Yashus\WPD\Rsync\RsyncOptions;
use Yashus\WPD\SSH\SSH;
use Yashus\WPD\Utils\Utils;
use Yashus\WPD\Wordpress\DBLocal;
use Yashus\WPD\Wordpress\DBRemote;

class PushDBHook extends AbstractHook
{


    private SSH $ssh;
    public string $dbExportPath;
    public bool $hasExportedDb = false;
    public bool $hasSearchReplacedDb = false;
    private DBRemote $dbRemote;
    private DBLocal $dbLocal;

    public function __construct(SSH $ssh,  HookArgs $hookArgs)
    {
        parent::__construct($hookArgs);
        $this->ssh = $ssh;
        $this->dbExportPath = Utils::getLocalWPDBImportExportPath($this->settings->docker) . DIRECTORY_SEPARATOR . $this->dbExportFilename;
        $this->dbRemote = new DBRemote(([
            'wpcli' => $this->settings['wpcli']['remote'],
            'wp_dir' => $this->remote['root'] . DIRECTORY_SEPARATOR . $this->remote['public']
        ]));
        // get local wordpress directory where wpcli should run
        $local_wp_dir = $this->settings->local->getPublicPath();
        $wpcli =  $this->settings['wpcli']['local'];
        // docker settings
        if ($this->settings->docker->isUsingDocker()) {
            $local_wp_dir = "./";
        }
        $this->dbLocal  = new DBLocal([
            'wpcli' => $wpcli,
            'wp_dir' => $local_wp_dir
        ]);
    }


    public function run(): PushDBHook
    {

        Console::header('Pushing DB to remote');
        $this->pushDb();
        $this->importRemoteDb();
        try {
            $this->exportLocalWPDatabase();
            $this->pushDb();
        } catch (\Exception $e) {
            if ($this->hasSearchReplacedDb) {
                $this->dbLocal->searchReplaceLocalDbUrl($this->remote, $this->settings->local);
            }
            throw $e;
        }
        return $this;
    }



    /**
     * Push db to tmp dir
     * 
     * @param string $dbExportPath - the absolute path to the exported db file
     * @return true 
     * @throws ProcessFailedException
     */
    public function pushDb(): true
    {
        $config = $this->ssh->getConfig();
        $user = $config['user'];
        $host = $config['host'];
        // check for db filename in docker
        $src = $this->settings->docker->getTmpDirMount() . DIRECTORY_SEPARATOR . $this->dbExportFilename;
        if (!file_exists($src)) {
            throw new \Exception('Failed to push db. Could not find db export at ' . $src);
        }
        $dest = "$user@$host:" . $this->remote->getPublicPath();
        $this->output->writeln("<comment>Pushing DB to remote</comment>");
        $this->io->definitionList(
            ['src' => $src],
            ['dest' => $dest]
        );
        return Rsync::run($src, $dest, new RsyncOptions([
            'dry_run' => false
        ]));
    }



    public function getDbExportPath(): string
    {
        return $this->dbExportPath;
    }

    public function searchReplaceDB(?array $options = ['revert' => false])
    {

        $revert = $options['revert'] ?? false;
        $fromEnv = $this->settings->local;
        $toEnv = $this->remote;
        if ($revert === true) {
            $fromEnv = $this->remote;
            $toEnv = $this->settings->local;
        }

        $search = $fromEnv['url'];
        $replace = $toEnv['url'];
        $this->output->writeln("DB Search replace ($search) -> ($replace)");
        $this->dbLocal->searchReplaceLocalDbUrl($fromEnv, $toEnv);
        $this->hasSearchReplacedDb = !$revert;
    }

    private function exportDB()
    {
        if (!$this->dbLocal->export($this->dbExportPath, $output)) {
            throw new \Exception('Failed to export db: ' . $output);
        }
        $this->hasExportedDb = true;
    }
    private function exportLocalWPDatabase()
    {

        $this->output->writeln('<comment>Preparing and exporting local database</comment>');
        // check if previous db export exists
        if (file_exists($this->dbExportPath)) {
            if (!Console::confirm("A database dump with the name {$this->dbExportFilename} already exists. Would you like to overwrite it?")) {
                return true;
            }
        }
        // search replace from local env to remote (staging/production)
        $this->searchReplaceDB();
        // export db to  the local root or the docker container tmp dir
        $this->exportDB();
        // log success
        $message = '<fg=green>✔️ Exported local database to ' . $this->dbExportPath . '</>';
        if ($this->settings->docker->isUsingDocker()) {
            $message .= ' in docker container';
        }
        $this->output->writeln($message);
        // revert search replace
        $this->searchReplaceDB(['revert' => true]);


        return true;
    }
    /**
     * Import remote db 
     * @return true 
     * @throws \Exception
     */
    public function importRemoteDb()
    {
        $ssh = $this->ssh->connect();
        $this->dbRemote->import($ssh, $this->dbExportFilename);
        $this->dbRemote->remove($ssh, $this->remote->getPublicPath() . DIRECTORY_SEPARATOR . $this->dbExportFilename);
        return true;
    }
}
