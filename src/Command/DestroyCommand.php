<?php

declare(strict_types=1);

// ABOUTME: Destroys all Docker services and volumes.
// ABOUTME: Runs docker-compose down -v to remove everything.

namespace Seaman\Command;

use Seaman\Service\DockerManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seaman:destroy',
    description: 'Destroy all services and volumes',
    aliases: ['destroy'],
)]
class DestroyCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectRoot = (string) getcwd();

        // Check if seaman.yaml exists
        if (!file_exists($projectRoot . '/seaman.yaml')) {
            $io->error('seaman.yaml not found. Run "seaman init" first.');
            return Command::FAILURE;
        }


        $io->warning('This will remove all containers, networks, and volumes!');

        if (!$io->confirm('Are you sure?', false)) {
            $io->info('Cancelled.');
            return Command::SUCCESS;
        }

        $manager = new DockerManager((string) getcwd());
        $result = $manager->destroy();

        if ($result->isSuccessful()) {
            $io->success('All services destroyed!');
            return Command::SUCCESS;
        }

        $io->error('Failed to destroy services');
        $io->writeln($result->errorOutput);

        return Command::FAILURE;
    }
}
