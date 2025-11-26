<?php

declare(strict_types=1);

// ABOUTME: Executes Symfony Console commands inside app container.
// ABOUTME: Passes all arguments to bin/console.

namespace Seaman\Command;

use Seaman\Service\DockerManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'exec:console',
    description: 'Run symfony console commands on application container',
    aliases: ['console'],
)]
class ConsoleCommand extends Command
{
    protected static $defaultName = 'console';
    protected static $defaultDescription = 'Run Symfony console commands';

    protected function configure(): void
    {
        $this->ignoreValidationErrors();
        $this->addArgument('args', InputArgument::IS_ARRAY, 'Console arguments');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $args = $input->getArgument('args');
        $manager = new DockerManager(getcwd());

        $result = $manager->executeInService('app', ['php', 'bin/console', ...$args]);
        $output->write($result->output);

        if (!$result->isSuccessful()) {
            $output->write($result->errorOutput);
        }

        return $result->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }
}