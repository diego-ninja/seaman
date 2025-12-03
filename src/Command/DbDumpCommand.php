<?php

declare(strict_types=1);

// ABOUTME: Dumps database content to a file.
// ABOUTME: Supports PostgreSQL, MySQL, MariaDB, SQLite, and MongoDB databases.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Enum\Service;
use Seaman\Service\ConfigManager;
use Seaman\Service\DockerManager;
use Seaman\UI\Terminal;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\ServiceConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\select;

#[AsCommand(
    name: 'db:dump',
    description: 'Export database to a file',
)]
class DbDumpCommand extends AbstractSeamanCommand implements Decorable
{
    public function __construct(
        private readonly ConfigManager $configManager,
        private readonly DockerManager $dockerManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'file',
            InputArgument::OPTIONAL,
            'Output file path (default: database_YYYYMMDD_HHMMSS.sql)',
        );

        $this->addOption(
            'service',
            's',
            InputOption::VALUE_REQUIRED,
            'Database service name',
        );
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

        $file = $input->getArgument('file');
        if (!is_string($file) || $file === '') {
            $extension = $databaseService->type === Service::MongoDB ? 'archive' : 'sql';
            $file = sprintf(
                '%s_dump_%s.%s',
                $databaseService->name,
                date('Ymd_His'),
                $extension,
            );
        }

        $dumpCommand = $this->getDumpCommand($databaseService);

        if ($dumpCommand === null) {
            Terminal::error("Unsupported database type: {$databaseService->type->value}");
            return Command::FAILURE;
        }

        try {
            $result = $this->dockerManager->executeInService(
                service: $databaseService->name,
                command: $dumpCommand,
                message: "Dumping {$databaseService->type->value} database to: {$file}",
            );
        } catch (\RuntimeException $e) {
            Terminal::error($e->getMessage());
            return Command::FAILURE;
        }

        if (!$result->isSuccessful()) {
            Terminal::error('Database dump failed:');
            Terminal::output()->writeln($result->errorOutput);
            return Command::FAILURE;
        }

        if (file_put_contents($file, $result->output) === false) {
            Terminal::error("Failed to write dump to file: {$file}");
            return Command::FAILURE;
        }

        Terminal::success("Database dumped successfully to: {$file}");

        return Command::SUCCESS;
    }

    /**
     * @return ServiceConfig|null
     */
    private function selectDatabaseService(Configuration $config, ?string $serviceName): ?ServiceConfig
    {
        $databases = array_filter(
            $config->services->all(),
            fn(ServiceConfig $s): bool => in_array($s->type->value, Service::databases(), true)
                && $s->type !== Service::None,
        );

        if ($serviceName !== null) {
            $service = array_find(
                $databases,
                fn(ServiceConfig $s): bool => $s->name === $serviceName,
            );

            if ($service === null) {
                throw new \RuntimeException("Service '{$serviceName}' not found");
            }

            return $service;
        }

        $databasesArray = array_values($databases);

        if (count($databasesArray) === 0) {
            return null;
        }

        if (count($databasesArray) === 1) {
            return $databasesArray[0];
        }

        // Multiple databases - ask user to select
        $choices = [];
        foreach ($databasesArray as $db) {
            $choices[$db->name] = sprintf('%s (%s)', $db->name, $db->type->value);
        }

        $selected = select(
            label: 'Select database service:',
            options: $choices,
        );

        return array_find(
            $databasesArray,
            fn(ServiceConfig $s): bool => $s->name === $selected,
        ) ?? $databasesArray[0];
    }

    /**
     * @param ServiceConfig $service
     * @return list<string>|null
     */
    private function getDumpCommand(ServiceConfig $service): ?array
    {
        $envVars = $service->environmentVariables;

        return match ($service->type) {
            Service::PostgreSQL => [
                'pg_dump',
                '-U',
                $envVars['POSTGRES_USER'] ?? 'postgres',
                $envVars['POSTGRES_DB'] ?? 'postgres',
            ],
            Service::MySQL, Service::MariaDB => [
                'mysqldump',
                '-u',
                $envVars['MYSQL_USER'] ?? 'root',
                '-p' . ($envVars['MYSQL_PASSWORD'] ?? ''),
                $envVars['MYSQL_DATABASE'] ?? 'mysql',
            ],
            Service::SQLite => [
                'sqlite3',
                $envVars['SQLITE_DATABASE'] ?? '/data/database.db',
                '.dump',
            ],
            Service::MongoDB => [
                'mongodump',
                '--username',
                $envVars['MONGO_INITDB_ROOT_USERNAME'] ?? 'root',
                '--password',
                $envVars['MONGO_INITDB_ROOT_PASSWORD'] ?? '',
                '--authenticationDatabase',
                'admin',
                '--archive',
            ],
            default => null,
        };
    }
}
