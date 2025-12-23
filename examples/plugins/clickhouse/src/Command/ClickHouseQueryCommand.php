<?php

declare(strict_types=1);

// ABOUTME: Command to execute SQL queries against ClickHouse.
// ABOUTME: Runs clickhouse-client inside the ClickHouse container.

namespace Seaman\Plugin\ClickHouse\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'clickhouse:query',
    description: 'Execute a SQL query against ClickHouse',
)]
final class ClickHouseQueryCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument(
                'query',
                InputArgument::OPTIONAL,
                'SQL query to execute (omit for interactive shell)',
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_REQUIRED,
                'Output format (TabSeparated, CSV, JSON, Pretty, etc.)',
                'PrettyCompact',
            )
            ->addOption(
                'database',
                'd',
                InputOption::VALUE_REQUIRED,
                'Database to use',
                'default',
            )
            ->setHelp(<<<'HELP'
Execute SQL queries against the ClickHouse database.

<info>Examples:</info>

  <comment>Interactive shell:</comment>
    seaman clickhouse:query

  <comment>Single query:</comment>
    seaman clickhouse:query "SELECT version()"

  <comment>Query with JSON output:</comment>
    seaman clickhouse:query "SELECT * FROM system.tables" --format=JSON

  <comment>Query specific database:</comment>
    seaman clickhouse:query "SHOW TABLES" --database=mydb

<info>Available formats:</info>
  TabSeparated, CSV, JSON, JSONEachRow, Pretty, PrettyCompact, Vertical
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $query = $input->getArgument('query');
        $format = $input->getOption('format');
        $database = $input->getOption('database');

        // Build the docker exec command
        $containerName = $this->getContainerName();

        if ($query === null) {
            // Interactive shell mode
            $io->note('Opening ClickHouse interactive shell...');
            $command = sprintf(
                'docker exec -it %s clickhouse-client --database=%s',
                escapeshellarg($containerName),
                escapeshellarg($database),
            );

            passthru($command, $exitCode);
            return $exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
        }

        // Single query mode
        $command = sprintf(
            'docker exec %s clickhouse-client --database=%s --format=%s --query=%s',
            escapeshellarg($containerName),
            escapeshellarg($database),
            escapeshellarg($format),
            escapeshellarg($query),
        );

        passthru($command, $exitCode);

        if ($exitCode !== 0) {
            $io->error('Query execution failed');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Get the ClickHouse container name based on project name.
     */
    private function getContainerName(): string
    {
        // Try to get project name from seaman.yaml
        $configPath = getcwd() . '/.seaman/seaman.yaml';

        if (file_exists($configPath)) {
            $content = file_get_contents($configPath);
            if ($content !== false && preg_match('/name:\s*["\']?([^"\'\n]+)/', $content, $matches)) {
                return $matches[1] . '-clickhouse';
            }
        }

        // Fallback: try to find any clickhouse container
        $output = shell_exec('docker ps --format "{{.Names}}" | grep clickhouse | head -1');

        if ($output !== null && trim($output) !== '') {
            return trim($output);
        }

        return 'clickhouse';
    }
}
