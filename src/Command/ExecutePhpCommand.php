<?php

declare(strict_types=1);

// ABOUTME: Executes PHP commands inside app container.
// ABOUTME: Passes all arguments to PHP interpreter.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Service\DockerManager;
use Seaman\UI\Terminal;
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
class ExecutePhpCommand extends AbstractSeamanCommand implements Decorable
{
    protected function configure(): void
    {
        $this->ignoreValidationErrors();
        $this->addArgument('args', InputArgument::IS_ARRAY, 'PHP arguments');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = (string) getcwd();

        /** @var list<string> $args */
        $args = $input->getArgument('args');
        $manager = new DockerManager($projectRoot);

        $result = $manager->executeInService('app', ['php', ...$args]);
        print_r($result);
        Terminal::output()->writeln($result->output);

        if (!$result->isSuccessful()) {
            Terminal::error($result->output);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
