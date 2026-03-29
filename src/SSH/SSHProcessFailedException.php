<?php

namespace Yashus\WPD\SSH;


class SSHProcessFailedException extends \Exception
{
    public function __construct(int $exit_code, string $cmd, string $output, ?\Throwable $previous = null)
    {
        $message = self::class . "SSH process failed. \n Exit code: $exit_code \n --------- \n Command: $cmd\n ------ \n Output:\n$output";
        parent::__construct($message, $exit_code, $previous);
    }
}
