<?php

namespace Yashus\WPD\Main\Push;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yashus\WPD\Console\Console;
use Yashus\WPD\Env\Env;
use Yashus\WPD\Files\FileInjector;
use Yashus\WPD\Main\AbstractMain;
use Yashus\WPD\Process\Process;
use Yashus\WPD\Types\Push\PushOptions;
use Yashus\WPD\Types\YASWPD\EnvSettings;
use Yashus\WPD\Utils\Utils;
use Yashus\WPD\Wordpress\DBLocal;
use Yashus\WPD\Types\YASWPD\Settings;

class PrePushHooks extends AbstractHook
{


    private string $dbExportPath;
    public bool $hasInjectedEnvFiles = false;
    public bool $hasExportedDb = false;
    public bool $hasSearchReplacedDb = false;



    /**
     * @param DBLocal $dbLocal
     * @param Settings $settings
     * @param EnvSettings $remote (staging or production)
     * @param string $dbExportFilename
     * 
     */
    public function __construct(array $hookArgs)
    {

        parent::__construct(...$hookArgs);
        $this->dbExportPath = Utils::getLocalWPDBImportExportPath($this->settings->docker) . DIRECTORY_SEPARATOR . $this->dbExportFilename;
    }

    public function run()
    {

        $fileInjector = new FileInjector($this->settings, $this->output);
        try {
            // 1. optionally run hooks
            $this->runHookScripts(Hooks::prePush);
            // 2. Prepare and export database (skip if user has opted not to push db)
            $this->options->getShouldPushDb() &&  $this->exportLocalWPDatabase();
            // 3. inject env files
            $fileInjector->run($this->env);
            $this->hasInjectedEnvFiles = true;
            $this->logAction(HookActions::HAS_INJECTED_ENV_FILES, true);
            // check injected wp-config matches env database
            $this->verifyWPConfigHasCorrectDB();
        } catch (\Exception $e) {
            // if any error occurs, we have to reverse any changes 
            // 1. perform a search replace back to local values
            if ($this->hasSearchReplacedDb) {
                $this->dbLocal->searchReplaceLocalDbUrl($this->remote, $this->settings->local);
            }
            // 2. reset env files to their local content
            if ($this->hasInjectedEnvFiles) {
                $fileInjector->run(new Env('local'));
            }
            throw $e;
        }
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
        $this->logAction(HookActions::HAS_SEARCH_REPLACED_DB, !$revert);
        $this->hasSearchReplacedDb = !$revert;
    }

    private function exportDB()
    {
        if (!$this->dbLocal->export($this->dbExportPath, $output)) {
            throw new \Exception('Failed to export db: ' . $output);
        }
        $this->hasExportedDb = true;
        $this->logAction(HookActions::HAS_EXPORTED_DB, true);
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
     * Before pushing, make sure the database name in the wp-config file
     * matches the env settings database name.
     * @return void 
     */
    private function verifyWPConfigHasCorrectDB()
    {
        // skip verification if wp-config is not included in files array
        if (empty($this->settings->files->hasFile('wp-config.php'))) {
            return true;
        }
        $env_db_name = escapeshellarg($this->settings->getEnvSettings($this->env)['db']);
        $wp_config_path = escapeshellarg($this->settings->getEnvSettings(new Env('local'))->getPublicPath() . DIRECTORY_SEPARATOR . 'wp-config.php');
        try {
            Process::fromShellCommandLine("cat $wp_config_path | grep $env_db_name", $output, $exit_code, true);
        } catch (\Exception $e) {
            throw new \Exception("injected wp-config.php did not have the correct database name ($env_db_name) for " . $this->env->__toString() . '. Please check your .yaswpd.json settings. ' . $e->getMessage());
        }
        $this->output->writeln('✔️ Verified injected wp-config.php file for ' . $this->env->__toString());

        return true;
    }
}
