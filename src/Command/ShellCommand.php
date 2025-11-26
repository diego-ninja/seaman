<?php

declare(strict_types=1);

// ABOUTME: Opens interactive shell in service container.
// ABOUTME: Defaults to 'app' service, supports other services.

namespace Seaman\Command;

use Seaman\Service\DockerManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seaman:shell',
    description: 'Open interactive shell in service',
    aliases: ['shell'],
)]
class ShellCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('service', InputArgument::OPTIONAL, 'Service name', 'app');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectRoot = (string) getcwd();

        // Check if seaman.yaml exists
        if (!file_exists($projectRoot . '/seaman.yaml')) {
            $io->error('seaman.yaml not found. Run "seaman init" first.');
            return Command::FAILURE;
        }

        /** @var string $service */
        $service = $input->getArgument('service');

        $manager = new DockerManager((string) getcwd());

        $io->info("Opening shell in {$service} service...");

        $exitCode = $manager->executeInteractive($service, ['sh']);

        if ($exitCode !== 0) {
            $io->error("Failed to open shell in {$service}. Process exited with code: {$exitCode}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
