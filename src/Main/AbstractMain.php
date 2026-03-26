<?php


namespace Yashus\WPD\Main;

use Yashus\WPD\Console\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function PHPUnit\Framework\isCallable;

abstract class AbstractMain extends Console
{


    public $actionCallback;

    public function __construct(InputInterface $input,  OutputInterface $output, ?SymfonyStyle $io)
    {
        parent::__construct($input, $output, $io);
    }


    public function setActionCallback($callback)
    {
        $this->actionCallback = $callback;
    }

    public function logAction(string $action, mixed $data = null)
    {
        if (isCallable($this->actionCallback)) {
            call_user_func($this->actionCallback, $action, $data);
        }
    }
}
