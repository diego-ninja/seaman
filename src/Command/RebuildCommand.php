<?php

declare(strict_types=1);

// ABOUTME: Rebuilds Docker images.
// ABOUTME: Runs docker-compose build with --no-cache.

namespace Seaman\Command;

use Seaman\Service\DockerManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
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
    protected function configure(): void
    {
        $this->addArgument('service', InputArgument::OPTIONAL, 'Specific service to rebuild');
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

        $io->info($service ? "Rebuilding service: {$service}..." : 'Rebuilding all services...');

        $result = $manager->rebuild($service);

        if ($result->isSuccessful()) {
            $io->success('Rebuild complete!');
            return Command::SUCCESS;
        }

        $io->error('Failed to rebuild');
        $io->writeln($result->errorOutput);

        return Command::FAILURE;
    }
}
