<?php

declare(strict_types=1);

// ABOUTME: Opens an interactive database client shell.
// ABOUTME: Supports PostgreSQL, MySQL, and MariaDB databases.

namespace Seaman\Command;

use Seaman\Service\ConfigManager;
use Seaman\Service\DockerManager;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\ServiceConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'db:shell',
    description: 'Open an interactive database client shell',
)]
class DbShellCommand extends Command
{
    public function __construct(
        private readonly ConfigManager $configManager,
        private readonly DockerManager $dockerManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $config = $this->configManager->load();
        } catch (\RuntimeException $e) {
            $io->error('Failed to load configuration: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $databaseService = $this->findDatabaseService($config);

        if ($databaseService === null) {
            $io->error('No database service found in configuration.');
            $io->note('Add a database service with: seaman service:add');
            return Command::FAILURE;
        }

        if (!$databaseService->enabled) {
            $io->error("Database service '{$databaseService->name}' is not enabled.");
            return Command::FAILURE;
        }

        $io->info("Opening {$databaseService->type} shell...");

        $shellCommand = $this->getShellCommand($databaseService);

        if ($shellCommand === null) {
            $io->error("Unsupported database type: {$databaseService->type}");
            return Command::FAILURE;
        }

        try {
            $exitCode = $this->dockerManager->executeInteractive($databaseService->name, $shellCommand);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        return $exitCode;
    }

    /**
     * @param Configuration $config
     * @return ServiceConfig|null
     */
    private function findDatabaseService(Configuration $config): ?ServiceConfig
    {
        $databaseTypes = ['postgresql', 'mysql', 'mariadb'];

        return array_find($config->services->all(), fn($service) => in_array($service->type, $databaseTypes, true));

    }

    /**
     * @param ServiceConfig $service
     * @return list<string>|null
     */
    private function getShellCommand(ServiceConfig $service): ?array
    {
        $envVars = $service->environmentVariables;

        return match ($service->type) {
            'postgresql' => [
                'psql',
                '-U',
                $envVars['POSTGRES_USER'] ?? 'postgres',
                $envVars['POSTGRES_DB'] ?? 'postgres',
            ],
            'mysql', 'mariadb' => [
                'mysql',
                '-u',
                $envVars['MYSQL_USER'] ?? 'root',
                '-p' . ($envVars['MYSQL_PASSWORD'] ?? ''),
                $envVars['MYSQL_DATABASE'] ?? 'mysql',
            ],
            default => null,
        };
    }
}
