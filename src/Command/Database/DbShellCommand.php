<?php

declare(strict_types=1);

// ABOUTME: Opens an interactive database client shell.
// ABOUTME: Supports PostgreSQL, MySQL, MariaDB, SQLite, and MongoDB databases.

namespace Seaman\Command\Database;

use Seaman\Command\Concern\SelectsDatabaseService;
use Seaman\Command\ModeAwareCommand;
use Seaman\Contract\DatabaseServiceInterface;
use Seaman\Contract\Decorable;
use Seaman\Service\ConfigManager;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\Service\DockerManager;
use Seaman\UI\Terminal;
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

        info("Opening {$databaseServiceConfig->type->value} shell...");

        $shellCommand = $service->getShellCommand($databaseServiceConfig);

        try {
            $exitCode = $this->dockerManager->executeInteractive($databaseServiceConfig->name, $shellCommand);
        } catch (\RuntimeException $e) {
            Terminal::error($e->getMessage());
            return Command::FAILURE;
        }

        return $exitCode;
    }
}
