<?php

declare(strict_types=1);

// ABOUTME: Stops Docker services.
// ABOUTME: Executes docker-compose stop for all or specific services.

namespace Seaman\Command;

use Seaman\Service\DockerManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'seaman:stop',
    description: 'Stop seaman stack services',
    aliases: ['stop'],
)]
class StopCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('service', InputArgument::OPTIONAL, 'Specific service to stop');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $service = $input->getArgument('service');

        $manager = new DockerManager(getcwd());

        $io->info($service ? "Stopping service: {$service}..." : 'Stopping all services...');

        $result = $manager->stop($service);

        if ($result->isSuccessful()) {
            $io->success($service ? "Service {$service} stopped!" : 'All services stopped!');
            return Command::SUCCESS;
        }

        $io->error('Failed to stop services');
        $io->writeln($result->errorOutput);

        return Command::FAILURE;
    }
}
