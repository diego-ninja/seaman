<?php

declare(strict_types=1);

// ABOUTME: Starts Docker services.
// ABOUTME: Executes docker-compose up for all or specific services.

namespace Seaman\Command;

use Seaman\Service\DockerManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seaman:start',
    description: 'Start seaman stack services',
    aliases: ['start'],
)]
class StartCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('service', InputArgument::OPTIONAL, 'Specific service to start');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var ?string $service */
        $service = $input->getArgument('service');

        $projectRoot = (string) getcwd();

        // Check if seaman.yaml exists
        if (!file_exists($projectRoot . '/seaman.yaml')) {
            $io->error('seaman.yaml not found. Run "seaman init" first.');
            return Command::FAILURE;
        }

        $manager = new DockerManager($projectRoot);

        $io->info($service ? "Starting service: {$service}..." : 'Starting all services...');

        try {
            $result = $manager->start($service);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        if ($result->isSuccessful()) {
            $io->success($service ? "Service {$service} started!" : 'All services started!');
            return Command::SUCCESS;
        }

        $io->error('Failed to start services');
        $io->writeln($result->errorOutput);

        return Command::FAILURE;
    }
}
