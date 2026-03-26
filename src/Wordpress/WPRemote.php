<?php


namespace Yashus\WPD\Wordpress;

use RuntimeException;

use phpseclib3\Net\SSH2;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Yashus\WPD\Types\YASWPD\EnvSettings;
use Yashus\WPD\Types\YASWPD\Settings;

class WPRemote
{

    public string $wpcli;
    public EnvSettings $remote;
    public function __construct(Settings $settings, EnvSettings $remote)
    {
        $this->wpcli =   implode(" ", $settings->wpcli['remote']);
        $this->remote = $remote;
    }

    /**
     * Set maintenace mode
     * @param SSH2 $ssh 
     * @param bool $activate 
     * @param mixed &$output 
     * @param mixed &$exit_code 
     * @return bool 
     * @throws \RuntimeException
     */
    public function setMaintenanceMode(SSH2 $ssh, bool $activate, &$output = null, &$exit_code = null)
    {
        $activate = $activate ? 'activate' : 'deactivate';
        $publicPath = $this->remote->getPublicPath();
        $output = $ssh->exec(
            "cd '$publicPath' || exit 1
            {$this->wpcli} --skip-themes --skip-plugins maintenance-mode $activate"
        );
        $exit_code = $ssh->getExitStatus();
        if ($exit_code !== 0) {
            throw new RuntimeException('Failed to set wordpress maintenance mode: ' . $output, $exit_code);
        }
        return $exit_code == 0;
    }


    /**
     * Set maintenace mode
     * @param SSH2 $ssh 
     * @param bool $activate 
     * @param mixed &$output 
     * @param mixed &$exit_code 
     * @return bool 
     * @throws \RuntimeException
     */
    public function flushCache(SSH2 $ssh, &$output = null, &$exit_code = null)
    {
        $publicPath = $this->remote->getPublicPath();
        $output = $ssh->exec(
            "cd '$publicPath' || exit 1
            {$this->wpcli} --skip-themes --skip-plugins cache flush"
        );
        $exit_code = $ssh->getExitStatus();
        if ($exit_code !== 0) {
            throw new RuntimeException('Failed to clear cache ' . $output, $exit_code);
        }
        return $exit_code == 0;
    }
}
