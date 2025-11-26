<?php

declare(strict_types=1);

// ABOUTME: Restarts Docker services.
// ABOUTME: Stops and starts services in sequence.

namespace Seaman\Command;

use Seaman\Service\DockerManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seaman:restart',
    description: 'Restart seaman stack services',
    aliases: ['restart'],
)]
class RestartCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('service', InputArgument::OPTIONAL, 'Specific service to restart');
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

        /** @var ?string $service */
        $service = $input->getArgument('service');

        $manager = new DockerManager((string) getcwd());

        $io->info($service ? "Restarting service: {$service}..." : 'Restarting all services...');

        $result = $manager->restart($service);

        if ($result->isSuccessful()) {
            $io->success($service ? "Service {$service} restarted!" : 'All services restarted!');
            return Command::SUCCESS;
        }

        $io->error('Failed to restart services');
        $io->writeln($result->errorOutput);

        return Command::FAILURE;
    }
}
