<?php

declare(strict_types=1);

// ABOUTME: Tests for ServiceAddCommand.
// ABOUTME: Validates interactive service addition functionality.

namespace Seaman\Tests\Integration\Command;

use Seaman\Command\ServiceAddCommand;
use Seaman\Service\ConfigManager;
use Seaman\Service\Container\MysqlService;
use Seaman\Service\Container\PostgresqlService;
use Seaman\Service\Container\RedisService;
use Seaman\Service\Container\ServiceRegistry;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @property string $tempDir
 * @property ConfigManager $configManager
 * @property ServiceRegistry $registry
 */
beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/seaman-test-' . uniqid();
    mkdir($this->tempDir, 0755, true);

    // Create a minimal seaman.yaml
    $yaml = <<<YAML
version: '1.0'
server:
  type: symfony
  port: 8000
php:
  version: '8.4'
  extensions: []
  xdebug:
    enabled: false
    ide_key: PHPSTORM
    client_host: host.docker.internal
services: {}
volumes:
  persist: []
YAML;

    file_put_contents($this->tempDir . '/seaman.yaml', $yaml);

    $this->configManager = new ConfigManager($this->tempDir);
    $this->registry = new ServiceRegistry();
    $this->registry->register(new MysqlService());
    $this->registry->register(new PostgresqlService());
    $this->registry->register(new RedisService());
});

afterEach(function () {
    /** @var string $tempDir */
    $tempDir = $this->tempDir;
    if (is_dir($tempDir)) {
        $files = glob($tempDir . '/{,.}*', GLOB_BRACE);
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        rmdir($tempDir);
    }
});

test('shows info message when all services are already enabled', function () {
    /** @var string $tempDir */
    $tempDir = $this->tempDir;

    // Create config with all services enabled
    $yaml = <<<YAML
version: '1.0'
server:
  type: symfony
  port: 8000
php:
  version: '8.4'
  extensions: []
  xdebug:
    enabled: false
    ide_key: PHPSTORM
    client_host: host.docker.internal
services:
  mysql:
    enabled: true
    type: mysql
    version: '8.0'
    port: 3306
  postgresql:
    enabled: true
    type: postgresql
    version: '14'
    port: 5432
  redis:
    enabled: true
    type: redis
    version: '7'
    port: 6379
volumes:
  persist: []
YAML;

    file_put_contents($tempDir . '/seaman.yaml', $yaml);

    /** @var ConfigManager $configManager */
    $configManager = new ConfigManager($tempDir);
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;

    $command = new ServiceAddCommand($configManager, $registry);
    $tester = new CommandTester($command);

    $tester->execute([]);

    expect($tester->getStatusCode())->toBe(0);
    expect($tester->getDisplay())->toContain('All services are already enabled');
});

test('adds single service to configuration', function () {
    /** @var ConfigManager $configManager */
    $configManager = $this->configManager;
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;
    /** @var string $tempDir */
    $tempDir = $this->tempDir;

    $command = new ServiceAddCommand($configManager, $registry);
    $tester = new CommandTester($command);

    // Simulate selecting mysql service
    $tester->setInputs(['mysql', 'no']);
    $tester->execute([]);

    expect($tester->getStatusCode())->toBe(0);

    // Verify the service was added to the config file
    $config = $configManager->load();
    expect($config->services->has('mysql'))->toBeTrue();
    expect($config->services->get('mysql')->enabled)->toBeTrue();
    expect($config->services->get('mysql')->type)->toBe('mysql');
});

test('adds multiple services to configuration', function () {
    /** @var ConfigManager $configManager */
    $configManager = $this->configManager;
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;

    $command = new ServiceAddCommand($configManager, $registry);
    $tester = new CommandTester($command);

    // Add mysql first
    $tester->setInputs(['mysql', 'no']);
    $tester->execute([]);

    // Add redis second (need to reload config)
    $tester2 = new CommandTester($command);
    $tester2->setInputs(['redis', 'no']);
    $tester2->execute([]);

    expect($tester2->getStatusCode())->toBe(0);

    // Verify both services were added
    $config = $configManager->load();
    expect($config->services->has('mysql'))->toBeTrue();
    expect($config->services->has('redis'))->toBeTrue();
});

test('regenerates .env file after adding services', function () {
    /** @var ConfigManager $configManager */
    $configManager = $this->configManager;
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;
    /** @var string $tempDir */
    $tempDir = $this->tempDir;

    $command = new ServiceAddCommand($configManager, $registry);
    $tester = new CommandTester($command);

    $tester->setInputs(['mysql', 'no']);
    $tester->execute([]);

    // Verify .env file was created
    expect(file_exists($tempDir . '/.env'))->toBeTrue();

    $envContent = file_get_contents($tempDir . '/.env');
    expect($envContent)->toContain('MYSQL_PORT=');
});

test('asks to start new services after adding', function () {
    /** @var ConfigManager $configManager */
    $configManager = $this->configManager;
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;

    $command = new ServiceAddCommand($configManager, $registry);
    $tester = new CommandTester($command);

    $tester->setInputs(['mysql', 'no']);
    $tester->execute([]);

    $output = $tester->getDisplay();
    expect($output)->toContain('Start new services now?');
});

test('shows success message when services are added', function () {
    /** @var ConfigManager $configManager */
    $configManager = $this->configManager;
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;

    $command = new ServiceAddCommand($configManager, $registry);
    $tester = new CommandTester($command);

    $tester->setInputs(['mysql', 'yes']);
    $tester->execute([]);

    $output = $tester->getDisplay();
    expect($output)->toContain('Services added successfully');
    expect($output)->toContain('Service starting not yet implemented');
});
