<?php

declare(strict_types=1);

// ABOUTME: Executes PHP commands inside app container.
// ABOUTME: Passes all arguments to PHP interpreter.

namespace Seaman\Command;

use Seaman\Service\DockerManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'exec:php',
    description: 'Run php commands on application container',
    aliases: ['php'],
)]
class PhpCommand extends Command
{
    protected function configure(): void
    {
        $this->ignoreValidationErrors();
        $this->addArgument('args', InputArgument::IS_ARRAY, 'PHP arguments');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $args = $input->getArgument('args');
        $manager = new DockerManager(getcwd());

        $result = $manager->executeInService('app', ['php', ...$args]);
        $output->write($result->output);

        if (!$result->isSuccessful()) {
            $output->write($result->errorOutput);
        }

        return $result->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }
}