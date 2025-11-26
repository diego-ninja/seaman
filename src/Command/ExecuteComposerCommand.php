<?php

declare(strict_types=1);

// ABOUTME: Executes Composer commands inside app container.
// ABOUTME: Passes all arguments directly to composer.

namespace Seaman\Command;

use Seaman\Service\DockerManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'exec:composer',
    description: 'Run composer commands on application container',
    aliases: ['composer'],
)]
class ExecuteComposerCommand extends Command
{
    protected function configure(): void
    {
        $this->ignoreValidationErrors();
        $this->addArgument('args', InputArgument::IS_ARRAY, 'Composer arguments');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = (string) getcwd();

        // Check if seaman.yaml exists
        if (!file_exists($projectRoot . '/seaman.yaml')) {
            $output->writeln('<error>seaman.yaml not found. Run "seaman init" first.</error>');
            return Command::FAILURE;
        }

        /** @var list<string> $args */
        $args = $input->getArgument('args');
        $manager = new DockerManager($projectRoot);

        $result = $manager->executeInService('app', ['composer', ...$args]);
        $output->write($result->output);

        if (!$result->isSuccessful()) {
            $output->write($result->errorOutput);
        }

        return $result->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }
}
