<?php

namespace Yashus\WPD\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Style\SymfonyStyle;

class Console
{

    protected InputInterface $input;
    protected OutputInterface $output;

    protected ?SymfonyStyle $io;

    public function __construct(InputInterface $input, OutputInterface $output, SymfonyStyle $io)
    {
        $this->input = $input;
        $this->output = $output;
        $this->io = $io;
    }

    /**
     * Accepts yes/no, y/n. Return is false
     */
    public  function confirm(string $question): bool
    {
        $helper = new QuestionHelper();
        $question = new ConfirmationQuestion($question . ' [y/n]', false);
        return $helper->ask($this->input, $this->output, $question);
    }

    public function read(string $question): string
    {
        $question = new Question($question, 'AcmeDemoBundle');
        $helper = new QuestionHelper();
        return $helper->ask($this->input, $this->output, $question);
    }

    public function choose(string $question, array $choices, int $defaultChoice = 0, string $errorMessage = "Invalid choice."): string
    {
        $helper = new QuestionHelper();
        $question = new ChoiceQuestion(
            $question,
            // choices can also be PHP objects that implement __toString() method
            $choices,
            $defaultChoice
        );
        $question->setErrorMessage($errorMessage);

        $choice = $helper->ask($this->input, $this->output, $question);
        return $choice;
    }

    public  function header(string $headerTitle)
    {
        $this->output->writeln("");
        $this->io->section($headerTitle);
    }
}
