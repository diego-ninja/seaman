<?php

declare(strict_types=1);

// ABOUTME: Tests for ServiceListCommand.
// ABOUTME: Validates service listing functionality.

namespace Seaman\Tests\Integration\Command;

use Seaman\Command\ServiceListCommand;
use Seaman\Service\ConfigManager;
use Seaman\Service\ConfigurationValidator;
use Seaman\Service\Container\MysqlService;
use Seaman\Service\Container\PostgresqlService;
use Seaman\Service\Container\RedisService;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\VolumeConfig;
use Seaman\ValueObject\XdebugConfig;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @property string $tempDir
 * @property ConfigManager $configManager
 * @property ServiceRegistry $registry
 */
beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/seaman-test-' . uniqid();
    mkdir($this->tempDir . '/.seaman', 0755, true);

    // Create a minimal seaman.yaml
    $yaml = <<<YAML
version: '1.0'
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
    environment: {}
volumes:
  persist: []
YAML;

    file_put_contents($this->tempDir . '/.seaman/seaman.yaml', $yaml);

    $this->registry = new ServiceRegistry();
    $this->registry->register(new MysqlService());
    $this->registry->register(new PostgresqlService());
    $this->registry->register(new RedisService());
    $this->configManager = new ConfigManager($this->tempDir, $this->registry, new ConfigurationValidator());
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
        // Remove .seaman directory
        if (is_dir($tempDir . '/.seaman')) {
            $seamanFiles = glob($tempDir . '/.seaman/{,.}*', GLOB_BRACE);
            if ($seamanFiles !== false) {
                foreach ($seamanFiles as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            rmdir($tempDir . '/.seaman');
        }
        rmdir($tempDir);
    }
});

test('lists all services with status', function () {
    /** @var ConfigManager $configManager */
    $configManager = $this->configManager;
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;

    $command = new ServiceListCommand($configManager, $registry);
    $tester = new CommandTester($command);

    $tester->execute([]);

    expect($tester->getStatusCode())->toBe(0);

    $output = $tester->getDisplay();

    expect($output)->toContain('MySQL');
    expect($output)->toContain('Redis');
    expect($output)->toContain('enabled');
    expect($output)->toContain('available');
});

test('shows enabled status for active services', function () {
    /** @var ConfigManager $configManager */
    $configManager = $this->configManager;
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;

    $command = new ServiceListCommand($configManager, $registry);
    $tester = new CommandTester($command);

    $tester->execute([]);

    $output = $tester->getDisplay();
    $lines = explode("\n", $output);

    $mysqlLine = null;
    foreach ($lines as $line) {
        if (str_contains($line, 'mysql') || str_contains($line, 'MySQL')) {
            $mysqlLine = $line;
            break;
        }
    }

    expect($mysqlLine)->not->toBeNull();
    expect($mysqlLine)->toContain('enabled');
});

test('shows available status for inactive services', function () {
    /** @var ConfigManager $configManager */
    $configManager = $this->configManager;
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;

    $command = new ServiceListCommand($configManager, $registry);
    $tester = new CommandTester($command);

    $tester->execute([]);

    $output = $tester->getDisplay();
    $lines = explode("\n", $output);

    $postgresLine = null;
    $redisLine = null;
    foreach ($lines as $line) {
        if (str_contains($line, 'postgresql') || str_contains($line, 'PostgreSQL')) {
            $postgresLine = $line;
        }
        if (str_contains($line, 'redis') || str_contains($line, 'Redis')) {
            $redisLine = $line;
        }
    }

    expect($postgresLine)->not->toBeNull();
    expect($postgresLine)->toContain('available');
    expect($redisLine)->not->toBeNull();
    expect($redisLine)->toContain('available');
});

test('displays ports for each service', function () {
    /** @var ConfigManager $configManager */
    $configManager = $this->configManager;
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;

    $command = new ServiceListCommand($configManager, $registry);
    $tester = new CommandTester($command);

    $tester->execute([]);

    $output = $tester->getDisplay();

    expect($output)->toContain('3306');
    expect($output)->toContain('5432');
    expect($output)->toContain('6379');
});
