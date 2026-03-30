<?php

namespace Yashus\WPD\Rsync;

class RsyncOptions
{

    public int $timeout;
    public string $dry_run;
    public array $exclude;

    /**
     * 
     * @param array $options  - ['timeout' => 3600, 'dry_run' => false]
     * @return void 
     */
    public function __construct(array $options)
    {
        $this->timeout = $options['timeout'] ?? 3600;
        $this->dry_run = $options['dry_run'] ?? false;
        $this->exclude = $options['exclude'] ?? [];
    }

    public function isDryRun(): bool
    {
        return $this->dry_run;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function hasExcludes(): bool
    {
        return !empty($this->exclude);
    }
}
