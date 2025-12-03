<?php

declare(strict_types=1);

// ABOUTME: Unit tests for DbShellCommand.
// ABOUTME: Tests database selection and shell command generation.

use Seaman\Command\DbShellCommand;
use Seaman\Enum\Service;
use Seaman\Service\ConfigManager;
use Seaman\Service\DockerManager;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;
use Symfony\Component\Console\Tester\CommandTester;

test('executes shell when single database exists', function () {
    $configManager = mock(ConfigManager::class);
    $dockerManager = mock(DockerManager::class);

    $serviceConfig = new ServiceConfig(
        name: 'db',
        enabled: true,
        type: Service::PostgreSQL,
        version: '15',
        port: 5432,
        additionalPorts: [],
        environmentVariables: [
            'POSTGRES_USER' => 'testuser',
            'POSTGRES_DB' => 'testdb',
        ],
    );

    $config = new Configuration(
        php: new PhpConfig('8.4', 9000, false, '/app'),
        services: new ServiceCollection([$serviceConfig]),
    );

    $configManager->shouldReceive('load')->once()->andReturn($config);

    $dockerManager->shouldReceive('executeInteractive')
        ->once()
        ->with('db', ['psql', '-U', 'testuser', 'testdb'])
        ->andReturn(0);

    $command = new DbShellCommand($configManager, $dockerManager);
    $tester = new CommandTester($command);

    $tester->execute([]);

    expect($tester->getStatusCode())->toBe(0);
});
