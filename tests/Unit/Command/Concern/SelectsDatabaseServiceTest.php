<?php

declare(strict_types=1);

// ABOUTME: Unit tests for database service selection shared by database commands.
// ABOUTME: Covers explicit, automatic, interactive, and invalid selection paths.

namespace Seaman\Tests\Unit\Command\Concern;

use Seaman\Command\AbstractSeamanCommand;
use Seaman\Command\Concern\SelectsDatabaseService;
use Seaman\Service\ConfigManager;
use Seaman\Service\ConfigurationValidator;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\Tests\Integration\TestHelper;
use Seaman\UI\HeadlessMode;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

/**
 * @param array<string, array<string, mixed>> $services
 */
function writeDatabaseSelectionConfig(string $projectRoot, array $services): void
{
    $config = [
        'project_name' => 'database-selection-test',
        'version' => '1.0',
        'project_type' => 'existing',
        'php' => [
            'version' => '8.4',
            'server' => 'symfony',
            'extensions' => [],
            'xdebug' => ['enabled' => false],
        ],
        'services' => $services,
        'volumes' => ['persist' => []],
    ];

    file_put_contents(
        $projectRoot . '/.seaman/seaman.yaml',
        Yaml::dump($config, 5, 2),
    );
}

/**
 * @return array<string, mixed>
 */
function databaseService(string $type, bool $enabled = true): array
{
    return [
        'enabled' => $enabled,
        'type' => $type,
        'version' => 'latest',
        'port' => 0,
        'environment' => [],
    ];
}

function createDatabaseSelectionTester(string $projectRoot): CommandTester
{
    $configManager = new ConfigManager(
        $projectRoot,
        new ServiceRegistry(),
        new ConfigurationValidator(),
    );

    $command = new class ($configManager) extends AbstractSeamanCommand {
        use SelectsDatabaseService;

        public function __construct(private readonly ConfigManager $configManager)
        {
            parent::__construct('test:database-selection');
        }

        protected function configure(): void
        {
            $this->addOption('service', null, InputOption::VALUE_REQUIRED);
        }

        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            $service = $this->loadAndSelectDatabase($input);
            if (is_int($service)) {
                return $service;
            }

            $output->writeln($service->name);

            return Command::SUCCESS;
        }

        protected function getConfigManager(): ConfigManager
        {
            return $this->configManager;
        }
    };

    return new CommandTester($command);
}

beforeEach(function () {
    HeadlessMode::reset();
    HeadlessMode::enable();

    $this->projectRoot = TestHelper::createTempDir();
});

afterEach(function () {
    HeadlessMode::reset();

    TestHelper::removeTempDir($this->projectRoot);
});

test('selects an explicitly requested database from multiple candidates', function () {
    writeDatabaseSelectionConfig($this->projectRoot, [
        'postgresql' => databaseService('postgresql'),
        'mongodb' => databaseService('mongodb'),
        'redis' => databaseService('redis'),
    ]);

    $tester = createDatabaseSelectionTester($this->projectRoot);
    $tester->execute(['--service' => 'mongodb']);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())->toContain('mongodb');
});

test('selects the only configured database without prompting', function () {
    writeDatabaseSelectionConfig($this->projectRoot, [
        'postgresql' => databaseService('postgresql'),
        'redis' => databaseService('redis'),
    ]);

    $tester = createDatabaseSelectionTester($this->projectRoot);
    $tester->execute([]);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())->toContain('postgresql');
});

test('uses the interactive choice when multiple databases are configured', function () {
    writeDatabaseSelectionConfig($this->projectRoot, [
        'postgresql' => databaseService('postgresql'),
        'mysql' => databaseService('mysql'),
    ]);
    HeadlessMode::preset(['Select database service:' => 'mysql']);

    $tester = createDatabaseSelectionTester($this->projectRoot);
    $tester->execute([]);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())->toContain('mysql');
});

test('fails when the explicitly requested database does not exist', function () {
    writeDatabaseSelectionConfig($this->projectRoot, [
        'postgresql' => databaseService('postgresql'),
    ]);

    $tester = createDatabaseSelectionTester($this->projectRoot);
    $tester->execute(['--service' => 'mysql']);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain("Service 'mysql' not found");
});

test('fails when the service option is not a string', function () {
    writeDatabaseSelectionConfig($this->projectRoot, [
        'postgresql' => databaseService('postgresql'),
    ]);

    $tester = createDatabaseSelectionTester($this->projectRoot);
    $tester->execute(['--service' => ['postgresql']]);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('Invalid service option');
});

test('fails when no database is configured and the option is absent', function () {
    writeDatabaseSelectionConfig($this->projectRoot, [
        'redis' => databaseService('redis'),
    ]);

    $tester = createDatabaseSelectionTester($this->projectRoot);
    $tester->execute([]);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())
        ->toContain('No database service found')
        ->toContain('seaman service:add');
});

test('fails when the selected database is disabled', function () {
    writeDatabaseSelectionConfig($this->projectRoot, [
        'postgresql' => databaseService('postgresql', false),
    ]);

    $tester = createDatabaseSelectionTester($this->projectRoot);
    $tester->execute([]);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain("Database service 'postgresql' is not enabled");
});

test('fails when the configuration cannot be loaded', function () {
    $tester = createDatabaseSelectionTester($this->projectRoot);
    $tester->execute([]);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('Failed to load configuration');
});
