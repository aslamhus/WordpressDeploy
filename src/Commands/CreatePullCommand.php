<?php
// @see https://symfony.com/doc/current/console.html 
// src/Command/CreateUserCommand.php
namespace Yashus\WPD\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yashus\WPD\Env\Env;
use Yashus\WPD\Main\PullDB;
use Yashus\WPD\Main\PullWPContent;
use Yashus\WPD\SSH\SSH;
use Yashus\WPD\Types\YASWPD\Settings;

// the name of the command is what users type after "php bin/console"
#[AsCommand(
    name: 'pull',
    // this short description is shown when running "php bin/console list"
    description: 'Pulls wp database or content',
    // this is shown when running the command with the "--help" option
    help: 'Pulls and imports wordpress database',
    // this allows you to show one or more usage examples (no need to add the command name)
    usages: ['db', 'pull-content'],

)]
class CreatePullCommand
{
    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        SymfonyStyle $io,

        #[Argument('The pull type: wp-content, db)')] string $type
    ): int {
        // ... put here the code to create the user
        // this method must return an integer number with the "exit status code"
        // of the command. You can also use these constants to make code more readable

        try {
            $settings = new Settings($_SERVER['YAS_WPD']);
            $sshConfig = $settings->ssh->getEnvConfig(new Env('production'));
            $ssh = new SSH($sshConfig);
            if (!$ssh->verifySSHAgentAuthentication()) {
                throw new \Exception('SSH agent not found. Please add an agent for ' . $sshConfig->getSSHLogin() . ' on port ' . $sshConfig['port']);
            }
            switch ($type) {
                case 'wp-content':
                    // optionally exclude plugins / themes / uploads
                    $exclude = [];
                    new PullWPContent($ssh, $settings,  $exclude, $input, $output, $io);
                    break;

                case 'db':
                    new PullDB($ssh, $settings, $input, $output, $io);
                    break;

                default:
                    return Command::INVALID; // equivalent to int(2)
            }
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            // For verbosity:
            // $output->writeln('<error>' . $e->getTraceAsString() . '</error>');
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }
}
