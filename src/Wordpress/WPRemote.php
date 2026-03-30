<?php


namespace Yashus\WPD\Wordpress;

use Exception;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Yashus\WPD\SSH\SSH;
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
     * @param SSH $ssh 
     * @param bool $activate 
     * @param mixed &$output 
     * @param mixed &$exit_code 
     * @return bool 
     * @throws \Exception
     */
    public function setMaintenanceMode(SSH $ssh, bool $activate, &$output = null, &$exit_code = null)
    {
        $activate = $activate ? 'activate' : 'deactivate';
        $publicPath = $this->remote->getPublicPath();
        try {
            $ssh->exec(
                "cd '$publicPath' || exit 1
            {$this->wpcli} --skip-themes --skip-plugins maintenance-mode $activate",
                $output,
                $exit_code
            );
        } catch (Exception $e) {
            throw new Exception('Failed to set wordpress maintenance mode', $exit_code, $e);
        }
        return true;
    }


    /**
     * Set maintenace mode
     * @param SSH $ssh 
     * @param bool $activate 
     * @param mixed &$output 
     * @param mixed &$exit_code 
     * @return bool 
     * @throws \Exception
     */
    public function flushCache(SSH $ssh, &$output = null, &$exit_code = null)
    {
        $publicPath = $this->remote->getPublicPath();
        try {
            $output = $ssh->exec(
                "cd '$publicPath' || exit 1
            {$this->wpcli} --skip-themes --skip-plugins cache flush"
            );
        } catch (Exception $e) {
            throw new Exception('Failed to flush cache');
        }
        return true;
    }
}
