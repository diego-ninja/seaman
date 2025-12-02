<?php

declare(strict_types=1);

// ABOUTME: Executes Composer commands inside app container.
// ABOUTME: Passes all arguments directly to composer.

namespace Seaman\Command;

use Seaman\Contracts\Decorable;
use Seaman\Service\DockerManager;
use Seaman\UI\Terminal;
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
class ExecuteComposerCommand extends AbstractSeamanCommand implements Decorable
{
    protected function configure(): void
    {
        $this->ignoreValidationErrors();
        $this->addArgument('args', InputArgument::IS_ARRAY, 'Composer arguments');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = (string) getcwd();

        /** @var list<string> $args */
        $args = $input->getArgument('args');
        $manager = new DockerManager($projectRoot);

        $result = $manager->executeInService('app', ['composer', ...$args]);
        Terminal::output()->writeln($result->output);

        if (!$result->isSuccessful()) {
            Terminal::error($result->output);
        }

        return $result->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }
}
