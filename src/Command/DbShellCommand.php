<?php

declare(strict_types=1);

// ABOUTME: Opens an interactive database client shell.
// ABOUTME: Supports PostgreSQL, MySQL, MariaDB, SQLite, and MongoDB databases.

namespace Seaman\Command;

use Seaman\Command\Concern\SelectsDatabaseService;
use Seaman\Contract\Decorable;
use Seaman\Enum\Service;
use Seaman\Service\ConfigManager;
use Seaman\Service\DockerManager;
use Seaman\UI\Terminal;
use Seaman\ValueObject\ServiceConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\info;

#[AsCommand(
    name: 'db:shell',
    description: 'Open an interactive database client shell',
)]
class DbShellCommand extends ModeAwareCommand implements Decorable
{
    use SelectsDatabaseService;

    public function __construct(
        private readonly ConfigManager $configManager,
        private readonly DockerManager $dockerManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'service',
            's',
            InputOption::VALUE_REQUIRED,
            'Database service name',
        );
    }

    protected function supportsMode(\Seaman\Enum\OperatingMode $mode): bool
    {
        return true; // Works in all modes
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $config = $this->configManager->load();
        } catch (\RuntimeException $e) {
            Terminal::error('Failed to load configuration: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $serviceName = $input->getOption('service');
        if (!is_string($serviceName) && $serviceName !== null) {
            Terminal::error('Invalid service option.');
            return Command::FAILURE;
        }

        try {
            $databaseService = $this->selectDatabaseService($config, $serviceName);
        } catch (\RuntimeException $e) {
            Terminal::error($e->getMessage());
            return Command::FAILURE;
        }

        if ($databaseService === null) {
            Terminal::error('No database service found in configuration.');
            Terminal::output()->writeln('Add a database service with: seaman service:add');
            return Command::FAILURE;
        }

        if (!$databaseService->enabled) {
            Terminal::error("Database service '{$databaseService->name}' is not enabled.");
            return Command::FAILURE;
        }

        info("Opening {$databaseService->type->value} shell...");

        $shellCommand = $this->getShellCommand($databaseService);

        if ($shellCommand === null) {
            Terminal::error("Unsupported database type: {$databaseService->type->value}");
            return Command::FAILURE;
        }

        try {
            $exitCode = $this->dockerManager->executeInteractive($databaseService->name, $shellCommand);
        } catch (\RuntimeException $e) {
            Terminal::error($e->getMessage());
            return Command::FAILURE;
        }

        return $exitCode;
    }

    /**
     * @return list<string>|null
     */
    private function getShellCommand(ServiceConfig $service): ?array
    {
        /** @var array<string, string> $envVars */
        $envVars = $service->environmentVariables;

        return match ($service->type) {
            Service::PostgreSQL => [
                'psql',
                '-U',
                $envVars['POSTGRES_USER'] ?? 'postgres',
                $envVars['POSTGRES_DB'] ?? 'postgres',
            ],
            Service::MySQL, Service::MariaDB => [
                'mysql',
                '-u',
                $envVars['MYSQL_USER'] ?? 'root',
                '-p' . ($envVars['MYSQL_PASSWORD'] ?? ''),
                $envVars['MYSQL_DATABASE'] ?? 'mysql',
            ],
            Service::SQLite => [
                'sqlite3',
                $envVars['SQLITE_DATABASE'] ?? '/data/database.db',
            ],
            Service::MongoDB => [
                'mongosh',
                '--username',
                $envVars['MONGO_INITDB_ROOT_USERNAME'] ?? 'root',
                '--password',
                $envVars['MONGO_INITDB_ROOT_PASSWORD'] ?? '',
                '--authenticationDatabase',
                'admin',
            ],
            default => null,
        };
    }
}
