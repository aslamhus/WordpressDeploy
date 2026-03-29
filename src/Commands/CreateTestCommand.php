<?php
// @see https://symfony.com/doc/current/console.html 
// src/Command/CreateUserCommand.php
namespace Yashus\WPD\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
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
    usages: ['list', '<testsuite-name>']

)]
class CreateTestCommand
{

    private array $testsuites = [];
    private OutputInterface $output;

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        SymfonyStyle $io,

        #[Option('List the available tests)')] bool $list = false,
        #[Argument('The suite to test)')] string $testsuite = "",

    ): int {
        $this->output = $output;
        $this->testsuites = $this->getTestSuites();
        // List the test suites
        if ($list === true) {
            $this->printTestSuites($output, $this->testsuites);
            return Command::SUCCESS;
        }

        if (!$this->verifyTestSuite($testsuite)) {
            if ($testsuite == 'list') {
                $this->output->writeln("Did you mean: 'yas-wpd test --list'?");
            }
            return Command::INVALID;
        }
        // Test a specific suite
        $phpUnitCmd = 'vendor/bin/phpunit --colors=always --testdox \
            -c vendor/yashus/wordpress-deploy/phpunit.xml';
        try {
            $p = Process::fromShellCommandLine("$phpUnitCmd --testsuite $testsuite", $commandOutput);
            if ($p) {
                $io->title('Testing ' . $testsuite);
                if ($this->output->isVerbose()) {
                    $this->output->write($commandOutput ?? "");
                }
                $this->output->writeln("✅ All tests passed!");
            }
        } catch (\Exception $e) {
            $this->output->writeln("❌ Some tests failed");
            $this->output->writeln("");
            $this->output->write($commandOutput);
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }

    /**
     * Get the available phpunit.xml testsuites 
     * and map them to the tests.json
     * @return array 
     * @throws mixed 
     */
    private function getTestSuites(): array
    {
        $testsuites = [];
        // get phpunit.xml
        $phpUnitXmlPath = __DIR__ . '/../../phpunit.xml';
        $phpUnitXml = file_get_contents($phpUnitXmlPath) or throw new \Exception('Could not find phpunit.xml at ' . realpath($phpUnitXmlPath));
        $xml = simplexml_load_string($phpUnitXml) or die("Error: Cannot create object");
        // get test.json
        $testsJsonPath = __DIR__ . '/../../tests/tests.json';
        $testsJson = json_decode(file_get_contents($testsJsonPath), true);

        foreach ($xml->testsuites->children() as $testsuite) {
            $name = (string) $testsuite['name'];

            $testData = $testsJson[$name];
            $testsuites[$name] = $testData;
            // look for test.json testsuite
        }
        return $testsuites;
    }

    private function printTestSuites()
    {
        $this->output->writeln("Test suites:");
        foreach ($this->testsuites as $name => $data) {

            $this->output->write("- <comment>{$name}</comment>: {$data['description']}");
            if (isset($data['warning'])) {
                $this->output->write("<fg=red>‼️ {$data['warning']}</>");
            }
            $this->output->write("\n");
        }
    }

    private function verifyTestSuite(string $testsuite)
    {
        if (!in_array($testsuite, array_keys($this->testsuites))) {

            $this->output->writeln("<error>Invalid test suite '$testsuite'</error>");
            $this->printTestSuites($this->testsuites);
            return false;
        }
        return true;
    }
}
