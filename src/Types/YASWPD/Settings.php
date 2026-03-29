<?php

namespace Yashus\WPD\Types\YASWPD;

use Yashus\WPD\Env\Env;

class Settings extends AbstractArraySettings
{

    public string $project;
    public SSHSettings $ssh;
    public EnvSettings $production;
    public EnvSettings $local;
    public ?EnvSettings $staging;
    public WPCLISettings $wpcli;
    public ComposerSettings $composer;
    public WordpressSettings $wordpress;
    public DockerSettings $docker;
    public FilesArray $files;
    public HooksSettings $hooks;
    public string $upload_type;
    public bool $dry_run;
    public string $timezone;

    public function __construct(array $settings)
    {

        $this->project = $settings['project'];
        $this->ssh = new SSHSettings($settings['ssh']);
        $this->production = new EnvSettings($settings['production']);
        $this->local = new EnvSettings($settings['local']);
        $this->staging = isset($settings['staging']) ? new EnvSettings($settings['staging']) : null;
        $this->wpcli = new WPCLISettings($settings['wpcli']);
        if (isset($settings['composer'])) {
            $this->composer = new ComposerSettings($settings['composer']);
        }
        $this->wordpress = new WordpressSettings($settings['wordpress']);
        $this->docker = new DockerSettings($settings['docker']);
        if (isset($settings['files'])) {
            $this->files = new FilesArray($settings['files']);
        }
        if (isset($settings['hooks'])) {
            $this->hooks = new HooksSettings($settings['hooks']);
        }
        $this->upload_type = $settings['upload_type'] ?? 'rsync';
        $this->dry_run = $settings['dry_run'] ?? false;
        $this->timezone = $settings['timezone'] ?? 'America/Vancouver';
        // push all set properties to data array for json serialization
        foreach (get_class_vars(self::class) as $key => $value) {
            if (!isset($this->{$key}) || $key == 'data') continue;
            $this->data[$key] = $this->{$key};
        }
    }

    public function getEnvSettings(Env $env): ?EnvSettings
    {
        return $this->offsetGet($env->__toString());
    }

    public function jsonSerialize(): mixed
    {
        // make sure to update all values!
        foreach (get_class_vars(self::class) as $key => $value) {
            if (!isset($this->{$key}) || $key == 'data') continue;
            $this->data[$key] = $this->{$key};
        }
        return $this->data;
    }
}
