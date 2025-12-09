<?php

declare(strict_types=1);

// ABOUTME: Dumps database content to a file.
// ABOUTME: Supports PostgreSQL, MySQL, MariaDB, SQLite, and MongoDB databases.

namespace Seaman\Command\Database;

use Seaman\Command\Concern\SelectsDatabaseService;
use Seaman\Command\ModeAwareCommand;
use Seaman\Contract\DatabaseServiceInterface;
use Seaman\Contract\Decorable;
use Seaman\Enum\Service;
use Seaman\Service\ConfigManager;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\Service\DockerManager;
use Seaman\UI\Terminal;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'db:dump',
    description: 'Export database to a file',
    aliases: ['dump'],
)]
class DbDumpCommand extends ModeAwareCommand implements Decorable
{
    use SelectsDatabaseService;

    public function __construct(
        private readonly ConfigManager $configManager,
        private readonly DockerManager $dockerManager,
        private readonly ServiceRegistry $registry,
    ) {
        parent::__construct();
    }

    protected function getConfigManager(): ConfigManager
    {
        return $this->configManager;
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

    public function supportsMode(\Seaman\Enum\OperatingMode $mode): bool
    {
        return true; // Works in all modes
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $databaseServiceConfig = $this->loadAndSelectDatabase($input);
        if (is_int($databaseServiceConfig)) {
            return $databaseServiceConfig;
        }

        $service = $this->registry->get($databaseServiceConfig->type);
        if (!$service instanceof DatabaseServiceInterface) {
            Terminal::error("Service '{$databaseServiceConfig->type->value}' does not support database operations.");
            return Command::FAILURE;
        }

        $file = $input->getArgument('file');
        if (!is_string($file) || $file === '') {
            $extension = $databaseServiceConfig->type === Service::MongoDB ? 'archive' : 'sql';
            $file = sprintf(
                '%s_dump_%s.%s',
                $databaseServiceConfig->name,
                date('Ymd_His'),
                $extension,
            );
        }

        $dumpCommand = $service->getDumpCommand($databaseServiceConfig);

        try {
            $result = $this->dockerManager->executeInService(
                service: $databaseServiceConfig->name,
                command: $dumpCommand,
                message: "Dumping {$databaseServiceConfig->type->value} database to: {$file}",
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
}
