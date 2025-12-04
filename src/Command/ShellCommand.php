<?php

declare(strict_types=1);

// ABOUTME: Opens interactive shell in service container.
// ABOUTME: Defaults to 'app' service, supports other services.

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
    name: 'seaman:shell',
    description: 'Open interactive shell in service',
    aliases: ['shell'],
)]
class ShellCommand extends ModeAwareCommand implements Decorable
{
    protected function configure(): void
    {
        $this->addArgument('service', InputArgument::OPTIONAL, 'Service name', 'app');
    }

    protected function supportsMode(\Seaman\Enum\OperatingMode $mode): bool
    {
        return true; // Works in all modes
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $service */
        $service = $input->getArgument('service');

        $manager = new DockerManager((string) getcwd());
        $exitCode = $manager->executeInteractive($service, ['fish']);

        if ($exitCode !== 0) {
            Terminal::error("Failed to open shell in {$service}. Process exited with code: {$exitCode}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
