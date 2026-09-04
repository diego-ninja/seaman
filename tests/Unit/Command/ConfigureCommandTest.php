<?php

declare(strict_types=1);

// ABOUTME: Unit tests for ConfigureCommand.
// ABOUTME: Validates interactive service configuration command.

namespace Seaman\Tests\Unit\Command;

use Seaman\Application;
use Seaman\Tests\Integration\TestHelper;
use Seaman\UI\HeadlessMode;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

beforeEach(function () {
    HeadlessMode::reset();
    HeadlessMode::enable();
    /** @phpstan-ignore property.notFound */
    $this->tempDir = TestHelper::createTempDir();
    $originalDir = getcwd();
    if ($originalDir === false) {
        throw new \RuntimeException('Failed to get current working directory');
    }
    /** @phpstan-ignore property.notFound */
    $this->originalDir = $originalDir;
    /** @phpstan-ignore argument.type */
    chdir($this->tempDir);
});

afterEach(function () {
    HeadlessMode::reset();
    /** @phpstan-ignore property.notFound, argument.type */
    chdir($this->originalDir);
    /** @phpstan-ignore property.notFound, argument.type */
    TestHelper::removeTempDir($this->tempDir);
});

test('configure command requires managed mode', function () {
    // Without seaman.yaml, mode is Uninitialized
    // and configure command should not be available

    $application = new Application();

    expect(fn() => $application->find('configure'))
        ->toThrow(\Seaman\Exception\CommandNotAvailableException::class);
});

test('configure command requires valid service name', function () {
    /** @phpstan-ignore property.notFound, argument.type */
    TestHelper::copyFixture('minimal-seaman.yaml', $this->tempDir);

    $application = new Application();
    $commandTester = new CommandTester($application->find('configure'));

    $commandTester->execute(['service' => 'nonexistent']);

    expect($commandTester->getStatusCode())->toBe(1);
    expect($commandTester->getDisplay())->toContain('not found');
});

test('configure command requires service to be enabled', function () {
    /** @phpstan-ignore property.notFound, argument.type */
    TestHelper::copyFixture('minimal-seaman.yaml', $this->tempDir);

    $application = new Application();
    $commandTester = new CommandTester($application->find('configure'));

    $commandTester->execute(['service' => 'postgresql']);

    expect($commandTester->getStatusCode())->toBe(1);
    expect($commandTester->getDisplay())->toContain('not enabled');
});

test('configure command updates service configuration', function () {
    /** @phpstan-ignore property.notFound, argument.type */
    TestHelper::copyFixture('database-seaman.yaml', $this->tempDir);

    HeadlessMode::preset([
        'Database name' => 'new_database',
        'Database user' => 'new_user',
        'Database password' => 'new_password',
        'PostgreSQL version' => '17',
        'Port' => '55432',
        'What would you like to do?' => 'none',
    ]);

    $application = new Application();
    $commandTester = new CommandTester($application->find('configure'));

    $commandTester->execute(['service' => 'postgresql']);

    expect($commandTester->getStatusCode())->toBe(0);
    expect($commandTester->getDisplay())->toContain('Configuration saved');

    /** @phpstan-ignore property.notFound, binaryOp.invalid */
    $yamlPath = $this->tempDir . '/.seaman/seaman.yaml';
    expect(file_exists($yamlPath))->toBeTrue();

    $content = file_get_contents($yamlPath);
    expect($content)->toContain("version: '17'")
        ->and($content)->toContain('port: 55432')
        ->and($content)->toContain('extensions:')
        ->and($content)->toContain('pdo_pgsql')
        ->and($content)->toContain('new_database')
        ->and($content)->toContain('new_user')
        ->and($content)->toContain('new_password');

    /** @phpstan-ignore property.notFound, binaryOp.invalid */
    $envPath = $this->tempDir . '/.env';
    $env = file_get_contents($envPath);
    expect($env)->toContain('DB_PORT=55432')
        ->and($env)->toContain('DB_NAME=new_database')
        ->and($env)->toContain('DB_USER=new_user')
        ->and($env)->toContain('DB_PASSWORD=new_password');

    /** @phpstan-ignore property.notFound, binaryOp.invalid */
    $composePath = $this->tempDir . '/docker-compose.yml';
    expect(file_exists($composePath))->toBeTrue();
});

test('configure command preserves the current password when left empty', function () {
    /** @phpstan-ignore property.notFound, argument.type */
    TestHelper::copyFixture('database-seaman.yaml', $this->tempDir);

    /** @phpstan-ignore property.notFound, binaryOp.invalid */
    $yamlPath = $this->tempDir . '/.seaman/seaman.yaml';
    $raw = Yaml::parseFile($yamlPath);
    expect($raw)->toBeArray();
    $raw['services']['postgresql']['config'] = [
        'version' => '16',
        'port' => 5432,
        'database' => 'seaman',
        'user' => 'seaman',
        'password' => 'existing-secret',
    ];
    file_put_contents($yamlPath, Yaml::dump($raw, 5, 2));

    HeadlessMode::preset([
        'What would you like to do?' => 'none',
    ]);

    $application = new Application();
    $commandTester = new CommandTester($application->find('configure'));
    $commandTester->execute(['service' => 'postgresql']);

    expect($commandTester->getStatusCode())->toBe(0)
        ->and(file_get_contents($yamlPath))->toContain('existing-secret');

    /** @phpstan-ignore property.notFound, binaryOp.invalid */
    $env = file_get_contents($this->tempDir . '/.env');
    expect($env)->toContain('DB_PASSWORD=existing-secret');
});

test('configure command preserves a legacy environment password when left empty', function () {
    /** @phpstan-ignore property.notFound, argument.type */
    TestHelper::copyFixture('database-seaman.yaml', $this->tempDir);

    /** @phpstan-ignore property.notFound, binaryOp.invalid */
    $yamlPath = $this->tempDir . '/.seaman/seaman.yaml';
    $raw = Yaml::parseFile($yamlPath);
    expect($raw)->toBeArray();
    $raw['services']['postgresql']['version'] = '15';
    $raw['services']['postgresql']['port'] = 55432;
    $raw['services']['postgresql']['environment'] = [
        'POSTGRES_DB' => 'legacy_database',
        'POSTGRES_USER' => 'legacy_user',
        'POSTGRES_PASSWORD' => 'legacy-secret',
    ];
    file_put_contents($yamlPath, Yaml::dump($raw, 5, 2));

    HeadlessMode::preset([
        'What would you like to do?' => 'none',
    ]);

    $application = new Application();
    $commandTester = new CommandTester($application->find('configure'));
    $commandTester->execute(['service' => 'postgresql']);

    $updated = Yaml::parseFile($yamlPath);
    expect($commandTester->getStatusCode())->toBe(0)
        ->and($updated)->toBeArray()
        ->and($updated['services']['postgresql']['config'] ?? null)->toMatchArray([
            'version' => '15',
            'port' => 55432,
            'database' => 'legacy_database',
            'user' => 'legacy_user',
            'password' => 'legacy-secret',
        ]);

    /** @phpstan-ignore property.notFound, binaryOp.invalid */
    $env = file_get_contents($this->tempDir . '/.env');
    expect($env)->toContain('DB_PORT=55432')
        ->and($env)->toContain('DB_NAME=legacy_database')
        ->and($env)->toContain('DB_USER=legacy_user')
        ->and($env)->toContain('DB_PASSWORD=legacy-secret');
});

test('configure command preserves legacy plugin secrets when left empty', function (
    string $serviceName,
    string $secretField,
    array $environment,
) {
    /** @phpstan-ignore property.notFound, argument.type */
    TestHelper::copyFixture('minimal-seaman.yaml', $this->tempDir);

    /** @phpstan-ignore property.notFound, binaryOp.invalid */
    $yamlPath = $this->tempDir . '/.seaman/seaman.yaml';
    $raw = Yaml::parseFile($yamlPath);
    expect($raw)->toBeArray();
    $raw['services'][$serviceName] = [
        'enabled' => true,
        'type' => $serviceName,
        'environment' => $environment,
    ];
    file_put_contents($yamlPath, Yaml::dump($raw, 5, 2));

    HeadlessMode::preset([
        'What would you like to do?' => 'none',
    ]);

    $application = new Application();
    $commandTester = new CommandTester($application->find('configure'));
    $commandTester->execute(['service' => $serviceName]);

    $updated = Yaml::parseFile($yamlPath);
    expect($commandTester->getStatusCode())->toBe(0)
        ->and($updated)->toBeArray()
        ->and($updated['services'][$serviceName]['config'][$secretField] ?? null)->toBe('legacy-secret');
})->with([
    'RabbitMQ' => ['rabbitmq', 'password', ['RABBITMQ_DEFAULT_PASS' => 'legacy-secret']],
    'Mercure publisher' => ['mercure', 'jwt_secret', ['MERCURE_PUBLISHER_JWT_KEY' => 'legacy-secret']],
    'Mercure subscriber' => ['mercure', 'jwt_secret', ['MERCURE_SUBSCRIBER_JWT_KEY' => 'legacy-secret']],
    'Soketi' => ['soketi', 'app_secret', ['SOKETI_DEFAULT_APP_SECRET' => 'legacy-secret']],
]);
