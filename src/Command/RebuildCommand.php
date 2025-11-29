<?php

declare(strict_types=1);

// ABOUTME: Rebuilds Docker images.
// ABOUTME: Builds image from .seaman/Dockerfile and restarts services.

namespace Seaman\Command;

use Seaman\Service\ConfigManager;
use Seaman\Service\DockerImageBuilder;
use Seaman\Service\DockerManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seaman:rebuild',
    description: 'Rebuild docker images',
    aliases: ['rebuild'],
)]
class RebuildCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectRoot = (string) getcwd();

        // Check if seaman.yaml exists
        if (!file_exists($projectRoot . '/.seaman/seaman.yaml')) {
            $io->error('seaman.yaml not found. Run "seaman init" first.');
            return Command::FAILURE;
        }

        // Load configuration to get PHP version
        $configManager = new ConfigManager($projectRoot);
        $config = $configManager->load();

        // Build Docker image
        $io->info('Building Docker image...');
        $builder = new DockerImageBuilder($projectRoot, $config->php->version);
        $buildResult = $builder->build();

        if (!$buildResult->isSuccessful()) {
            $io->error('Failed to build Docker image');
            $io->writeln($buildResult->errorOutput);
            return Command::FAILURE;
        }

        $io->success('Docker image built successfully!');

        // Restart services
        $manager = new DockerManager($projectRoot);

        $io->info('Stopping services...');
        $stopResult = $manager->stop();

        if (!$stopResult->isSuccessful()) {
            $io->warning('Failed to stop services (they may not be running)');
        }

        $io->info('Starting services...');
        $startResult = $manager->start();

        if ($startResult->isSuccessful()) {
            $io->success('Rebuild and restart complete!');
            return Command::SUCCESS;
        }

        $io->error('Failed to start services');
        $io->writeln($startResult->errorOutput);
        return Command::FAILURE;
    }
}
