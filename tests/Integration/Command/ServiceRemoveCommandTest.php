<?php

declare(strict_types=1);

// ABOUTME: Tests for ServiceRemoveCommand.
// ABOUTME: Validates service removal functionality.

namespace Seaman\Tests\Integration\Command;

use Seaman\Command\ServiceRemoveCommand;
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

    // Create a minimal seaman.yaml with some enabled services
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
  redis:
    enabled: true
    type: redis
    version: '7'
    port: 6379
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

test('shows info message when no services are enabled', function () {
    /** @var string $tempDir */
    $tempDir = $this->tempDir;

    // Create config with no enabled services
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

    file_put_contents($tempDir . '/seaman.yaml', $yaml);

    /** @var ConfigManager $configManager */
    $configManager = new ConfigManager($tempDir);
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;

    $command = new ServiceRemoveCommand($configManager, $registry);
    $tester = new CommandTester($command);

    $tester->execute([]);

    expect($tester->getStatusCode())->toBe(0);
    expect($tester->getDisplay())->toContain('No services are currently enabled');
});

test('removes single service from configuration', function () {
    /** @var ConfigManager $configManager */
    $configManager = $this->configManager;
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;

    $command = new ServiceRemoveCommand($configManager, $registry);
    $tester = new CommandTester($command);

    // Simulate selecting mysql service and confirming removal
    $tester->setInputs(['mysql', 'yes', 'no']);
    $tester->execute([]);

    expect($tester->getStatusCode())->toBe(0);

    // Verify the service was removed from the config file
    $config = $configManager->load();
    expect($config->services->has('mysql'))->toBeFalse();
    expect($config->services->has('redis'))->toBeTrue();
});

test('removes multiple services from configuration', function () {
    /** @var ConfigManager $configManager */
    $configManager = $this->configManager;
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;

    $command = new ServiceRemoveCommand($configManager, $registry);
    $tester = new CommandTester($command);

    // Remove mysql first
    $tester->setInputs(['mysql', 'yes', 'no']);
    $tester->execute([]);

    // Remove redis second
    $tester2 = new CommandTester($command);
    $tester2->setInputs(['redis', 'yes', 'no']);
    $tester2->execute([]);

    expect($tester2->getStatusCode())->toBe(0);

    // Verify both services were removed
    $config = $configManager->load();
    expect($config->services->has('mysql'))->toBeFalse();
    expect($config->services->has('redis'))->toBeFalse();
});

test('regenerates .env file after removing services', function () {
    /** @var ConfigManager $configManager */
    $configManager = $this->configManager;
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;
    /** @var string $tempDir */
    $tempDir = $this->tempDir;

    $command = new ServiceRemoveCommand($configManager, $registry);
    $tester = new CommandTester($command);

    $tester->setInputs(['mysql', 'yes', 'no']);
    $tester->execute([]);

    // Verify .env file still exists but doesn't contain mysql
    expect(file_exists($tempDir . '/.env'))->toBeTrue();

    $envContent = file_get_contents($tempDir . '/.env');
    expect($envContent)->not->toContain('MYSQL_PORT=');
    expect($envContent)->toContain('REDIS_PORT=');
});

test('asks for confirmation before removing', function () {
    /** @var ConfigManager $configManager */
    $configManager = $this->configManager;
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;

    $command = new ServiceRemoveCommand($configManager, $registry);
    $tester = new CommandTester($command);

    $tester->setInputs(['mysql', 'no']);
    $tester->execute([]);

    $output = $tester->getDisplay();
    expect($output)->toContain('Are you sure you want to remove');

    // Verify service was NOT removed when answering no
    $config = $configManager->load();
    expect($config->services->has('mysql'))->toBeTrue();
});

test('asks to stop removed services', function () {
    /** @var ConfigManager $configManager */
    $configManager = $this->configManager;
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;

    $command = new ServiceRemoveCommand($configManager, $registry);
    $tester = new CommandTester($command);

    $tester->setInputs(['mysql', 'yes', 'no']);
    $tester->execute([]);

    $output = $tester->getDisplay();
    expect($output)->toContain('Stop removed services now?');
});

test('shows success message when services are removed', function () {
    /** @var ConfigManager $configManager */
    $configManager = $this->configManager;
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;

    $command = new ServiceRemoveCommand($configManager, $registry);
    $tester = new CommandTester($command);

    $tester->setInputs(['mysql', 'yes', 'yes']);
    $tester->execute([]);

    $output = $tester->getDisplay();
    expect($output)->toContain('Services removed successfully');
    expect($output)->toContain('Service stopping not yet implemented');
});
