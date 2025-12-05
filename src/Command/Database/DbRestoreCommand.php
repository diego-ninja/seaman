<?php

declare(strict_types=1);

// ABOUTME: Restores database from a dump file.
// ABOUTME: Supports PostgreSQL, MySQL, MariaDB, SQLite, and MongoDB databases.

namespace Seaman\Command\Database;

use Seaman\Command\Concern\SelectsDatabaseService;
use Seaman\Command\ModeAwareCommand;
use Seaman\Contract\Decorable;
use Seaman\Service\ConfigManager;
use Seaman\Service\DatabaseCommandBuilder;
use Seaman\Service\DockerManager;
use Seaman\UI\Terminal;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

#[AsCommand(
    name: 'db:restore',
    description: 'Restore database from a dump file',
    aliases: ['restore'],
)]
class DbRestoreCommand extends ModeAwareCommand implements Decorable
{
    use SelectsDatabaseService;

    public function __construct(
        private readonly ConfigManager $configManager,
        private readonly DockerManager $dockerManager,
        private readonly DatabaseCommandBuilder $commandBuilder = new DatabaseCommandBuilder(),
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

    public function supportsMode(\Seaman\Enum\OperatingMode $mode): bool
    {
        return true; // Works in all modes
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $databaseService = $this->loadAndSelectDatabase($input);
        if (is_int($databaseService)) {
            return $databaseService;
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

        $restoreCommand = $this->commandBuilder->restore($databaseService);

        if ($restoreCommand === null) {
            Terminal::error("Unsupported database type: {$databaseService->type->value}");
            return Command::FAILURE;
        }

        try {
            $result = $this->dockerManager->executeInServiceWithStdin(
                service: $databaseService->name,
                command: $restoreCommand,
                stdin: $dumpContent,
                message: "Restoring {$databaseService->type->value} database from: {$file}",
            );

            if (!$result->isSuccessful()) {
                Terminal::error('Database restore failed:');
                Terminal::output()->writeln($result->errorOutput);
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            Terminal::error("Restore failed: {$e->getMessage()}");
            return Command::FAILURE;
        }

        Terminal::success('Database restored successfully.');

        return Command::SUCCESS;
    }
}
