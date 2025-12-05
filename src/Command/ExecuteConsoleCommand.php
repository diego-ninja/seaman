<?php

declare(strict_types=1);

// ABOUTME: Executes Symfony Console commands inside app container.
// ABOUTME: Passes all arguments to bin/console.

namespace Seaman\Command;

use Seaman\Command\Concern\ExecutesInContainer;
use Seaman\Contract\Decorable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'exec:console',
    description: 'Run symfony console commands on application container',
    aliases: ['console'],
)]
class ExecuteConsoleCommand extends ModeAwareCommand implements Decorable
{
    use ExecutesInContainer;

    protected function configure(): void
    {
        $this->ignoreValidationErrors();
        $this->addArgument('args', InputArgument::IS_ARRAY, 'Console arguments');
    }

    public function supportsMode(\Seaman\Enum\OperatingMode $mode): bool
    {
        return true; // Works in all modes
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $args */
        $args = $input->getArgument('args');

        return $this->executeInContainer(['php', 'bin/console', ...$args]);
    }
}
