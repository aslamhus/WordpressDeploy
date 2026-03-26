<?php
// @see https://symfony.com/doc/current/console.html 
// src/Command/CreateUserCommand.php
namespace Yashus\WPD\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yashus\WPD\SSH\SSH;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yashus\WPD\Env\Env;
use Yashus\WPD\Main\Push\Push;
use Yashus\WPD\Process\Process;

// the name of the command is what users type after "php bin/console"
#[AsCommand(
    name: 'test',
    // this short description is shown when running "php bin/console list"
    description: 'Test your wordpress deploy settings',
    // this is shown when running the command with the "--help" option
    help: 'Test your wordpress deploy settings. Run this in your project root where both your .yaswpd.json file and vendor directory exists.',
    // this allows you to show one or more usage examples (no need to add the command name)

)]
class CreateTestCommand
{


    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        SymfonyStyle $io,
        #[Argument('The deploy environment)')] string $env = 'local'
    ): int {
        // ... put here the code to create the user
        // this method must return an integer number with the "exit status code"
        // of the command. You can also use these constants to make code more readable
        try {
            $p = Process::fromShellCommandLine('vendor/bin/phpunit --colors=always --testdox -c vendor/yashus/wordpress-deploy/phpunit.xml vendor/yashus/wordpress-deploy/tests', $commandOutput);
            if ($p) {
                if ($output->isVerbose()) {
                    $output->write($commandOutput ?? "");
                }
                $output->writeln("✅ All tests passed!");
            }
        } catch (\Exception $e) {
            $output->writeln("❌ Some tests failed");
            echo $commandOutput;
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }
}
