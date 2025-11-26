<?php

declare(strict_types=1);

// ABOUTME: Dumps database content to a file.
// ABOUTME: Supports PostgreSQL, MySQL, and MariaDB databases.

namespace Seaman\Command;

use Seaman\Service\ConfigManager;
use Seaman\Service\DockerManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'db:dump',
    description: 'Export database to a file',
)]
class DbDumpCommand extends Command
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

        $file = $input->getArgument('file');
        if (!is_string($file) || $file === '') {
            $file = sprintf(
                'database_%s.sql',
                date('Ymd_His'),
            );
        }

        $io->info("Dumping database to: {$file}");

        $dumpCommand = $this->getDumpCommand($databaseService);

        if ($dumpCommand === null) {
            $io->error("Unsupported database type: {$databaseService->type}");
            return Command::FAILURE;
        }

        try {
            $result = $this->dockerManager->executeInService($databaseService->name, $dumpCommand);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        if (!$result->isSuccessful()) {
            $io->error('Database dump failed:');
            $io->writeln($result->errorOutput);
            return Command::FAILURE;
        }

        if (file_put_contents($file, $result->output) === false) {
            $io->error("Failed to write dump to file: {$file}");
            return Command::FAILURE;
        }

        $io->success("Database dumped successfully to: {$file}");

        return Command::SUCCESS;
    }

    /**
     * @param \Seaman\ValueObject\Configuration $config
     * @return \Seaman\ValueObject\ServiceConfig|null
     */
    private function findDatabaseService($config): ?\Seaman\ValueObject\ServiceConfig
    {
        $databaseTypes = ['postgresql', 'mysql', 'mariadb'];

        foreach ($config->services->all() as $service) {
            if (in_array($service->type, $databaseTypes, true)) {
                return $service;
            }
        }

        return null;
    }

    /**
     * @param \Seaman\ValueObject\ServiceConfig $service
     * @return list<string>|null
     */
    private function getDumpCommand(\Seaman\ValueObject\ServiceConfig $service): ?array
    {
        $envVars = $service->environmentVariables;

        return match ($service->type) {
            'postgresql' => [
                'pg_dump',
                '-U',
                $envVars['POSTGRES_USER'] ?? 'postgres',
                $envVars['POSTGRES_DB'] ?? 'postgres',
            ],
            'mysql', 'mariadb' => [
                'mysqldump',
                '-u',
                $envVars['MYSQL_USER'] ?? 'root',
                '-p' . ($envVars['MYSQL_PASSWORD'] ?? ''),
                $envVars['MYSQL_DATABASE'] ?? 'mysql',
            ],
            default => null,
        };
    }
}
