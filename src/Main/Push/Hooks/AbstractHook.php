<?php


namespace Yashus\WPD\Main\Push\Hooks;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Yashus\WPD\Env\Env;
use Yashus\WPD\Types\YASWPD\EnvSettings;
use Yashus\WPD\Types\YASWPD\Settings;
use Yashus\WPD\Wordpress\DBLocal;
use Yashus\WPD\Types\Push\PushOptions;
use Yashus\WPD\Main\AbstractMain;
use Yashus\WPD\Process\Process;

abstract class AbstractHook extends AbstractMain implements HookInterface
{

    protected Env $env;
    protected Settings $settings;
    protected EnvSettings $remote;
    protected PushOptions $options;
    protected string $dbExportFilename;
    public string $archiveFilename;
    public string $deployIgnore;

    public function __construct(HookArgs $hookArgs)
    {
        parent::__construct($hookArgs->input, $hookArgs->output, $hookArgs->io);
        $this->env = $hookArgs->env;
        $this->settings = $hookArgs->settings;
        $this->remote = $hookArgs->remote;
        $this->options = $hookArgs->options;
        $this->output = $hookArgs->output;
        $this->dbExportFilename = $hookArgs->dbExportFilename;
        $this->archiveFilename = $hookArgs->archiveFilename;
        $this->deployIgnore = $hookArgs->deployIgnore;
    }
    protected function hasHooks(string $hookName)
    {
        return isset($this->settings->hooks[$hookName]);
    }


    /**
     * Run hook scripts
     * @param string $hookName 
     * @return null|array 
     * @throws \ProcessFailedException
     */
    protected function runHookScripts(string $hookName = Hooks::prePush): ?array
    {
        if (!$this->hasHooks($hookName)) return null;
        $scripts = $this->settings?->hooks[$hookName];
        if (!empty($scripts)) {
            foreach ($scripts as $script) {
                $scriptPath = getcwd() . DIRECTORY_SEPARATOR . ltrim($script, './');
                $this->output->writeln("<comment>Running $script</comment>");
                try {
                    Process::run([$scriptPath], null, $output, $exit_code, false, ['settings' => json_encode($this->settings)]);
                } catch (\Exception $e) {
                    throw $e;
                    // throw new \Exception("Error running $hookName script '$script'", $exit_code, $e);
                }
                $this->output->write($output);
                $this->output->writeln("✔️ Executed $script");
            }
        }

        $this->output->writeln('Succesfully ran ' . count($scripts) . ' script(s)');
        return $scripts;
    }
}
