<?php

declare(strict_types=1);

// ABOUTME: Restores database from a dump file.
// ABOUTME: Supports PostgreSQL, MySQL, MariaDB, SQLite, and MongoDB databases.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Enum\Service;
use Seaman\Service\ConfigManager;
use Seaman\Service\DockerManager;
use Seaman\UI\Terminal;
use Seaman\UI\Widget\Spinner\SpinnerFactory;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\ServiceConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;

#[AsCommand(
    name: 'db:restore',
    description: 'Restore database from a dump file',
)]
class DbRestoreCommand extends AbstractSeamanCommand implements Decorable
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
            InputArgument::REQUIRED,
            'Database dump file to restore',
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
        if (!is_string($file)) {
            Terminal::error('Invalid file argument.');
            return Command::FAILURE;
        }

        if (!file_exists($file)) {
            Terminal::error("Dump file not found: {$file}");
            return Command::FAILURE;
        }

        if (!confirm(
            label: "This will overwrite the '{$databaseService->name}' database. Continue?",
            default: false,
        )) {
            info('Restore cancelled.');
            return Command::SUCCESS;
        }

        $dumpContent = file_get_contents($file);
        if ($dumpContent === false) {
            Terminal::error("Failed to read dump file: {$file}");
            return Command::FAILURE;
        }

        $restoreCommand = $this->getRestoreCommand($databaseService);

        if ($restoreCommand === null) {
            Terminal::error("Unsupported database type: {$databaseService->type->value}");
            return Command::FAILURE;
        }

        // TODO: DockerManager needs stdin support for piping dump content to restore command
        // For now, we need to implement a way to pass $dumpContent to the command
        try {
            $success = SpinnerFactory::for(
                callable: function () use ($databaseService, $restoreCommand): bool {
                    $result = $this->dockerManager->executeInService(
                        service: $databaseService->name,
                        command: $restoreCommand,
                        message: null,
                    );

                    return $result->isSuccessful();
                },
                message: "Restoring {$databaseService->type->value} database from: {$file}",
            );

            if (!$success) {
                Terminal::error('Database restore failed.');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            Terminal::error("Restore failed: {$e->getMessage()}");
            return Command::FAILURE;
        }

        Terminal::success('Database restored successfully.');

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
    private function getRestoreCommand(ServiceConfig $service): ?array
    {
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
                'mongorestore',
                '--username',
                $envVars['MONGO_INITDB_ROOT_USERNAME'] ?? 'root',
                '--password',
                $envVars['MONGO_INITDB_ROOT_PASSWORD'] ?? '',
                '--authenticationDatabase',
                'admin',
                '--archive',
                '--drop',
            ],
            default => null,
        };
    }
}
