<?php
// @see https://symfony.com/doc/current/console.html 
namespace Yashus\WPD\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yashus\WPD\SSH\SSH;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yashus\WPD\Env\Env;
use Yashus\WPD\Main\Push\Push;
use Yashus\WPD\Types\Push\PushOptions;
use Yashus\WPD\Types\YASWPD\Settings;




// the name of the command is what users type after "php bin/console"
#[AsCommand(
    name: 'push',
    // this short description is shown when running "php bin/console list"
    description: 'Push wordpress to staging / production',
    // this is shown when running the command with the "--help" option
    help: 'Push wordpress to staging / production',
    // this allows you to show one or more usage examples (no need to add the command name)
    usages: ['staging', 'production'],

)]
class CreatePushCommand
{

    private Push $push;

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        SymfonyStyle $io,

        #[Argument('The deploy environment)')] string $env
    ): int {
        // init env type and default push options
        $env = new Env($env);
        $options = new PushOptions([
            'shouldPushDb' => true,
            'shouldPushArchive' => true,
        ]);
        // handle sign int
        pcntl_signal(SIGINT, [$this, 'sigIntHandler']);
        try {

            // get the settings
            $settings = new Settings($_SERVER['YAS_WPD']);
            // verify ssh agent

            $sshConfig = $settings->ssh->getEnvConfig($env);
            $ssh = new SSH($sshConfig);
            // if (!$ssh->verifySSHAgentAuthentication()) {
            //     throw new \Exception('SSH agent not found. Please add an agent for ' . $sshConfig->getSSHLogin() . ' on port ' . $sshConfig['port']);
            // }
            $args = [$env, $settings, $ssh, $options, $input, $output, $io];

            switch ($env) {
                case Env::type['staging']:
                    // push to staging
                    $this->push = new Push(...$args);
                    break;

                case Env::type['production']:
                    // push to production
                    $this->push = new Push(...$args);
                    break;

                default:
                    $output->writeln('Unknown env type: ' . $env);
                    return Command::INVALID; // equivalent to int(2)
            }
            $this->push->run();
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            // For verbosity:
            // $output->writeln('<error>' . $e->getTraceAsString() . '</error>');
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }

    public function sigIntHandler(int $signo, mixed $siginfo)
    {
        $this->push->handleSigint($signo, $siginfo);
        die;
    }
}
