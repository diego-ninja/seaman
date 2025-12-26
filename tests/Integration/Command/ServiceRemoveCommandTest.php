<?php

declare(strict_types=1);

// ABOUTME: Tests for ServiceRemoveCommand.
// ABOUTME: Validates service removal functionality.

namespace Seaman\Tests\Integration\Command;

use Seaman\Service\ConfigManager;
use Seaman\Service\ConfigurationValidator;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\ValueObject\Configuration;

/**
 * @property string $tempDir
 * @property ConfigManager $configManager
 * @property ServiceRegistry $registry
 */
beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/seaman-test-' . uniqid();
    mkdir($this->tempDir . '/.seaman', 0755, true);

    // Create a minimal seaman.yaml with some enabled services
    $yaml = <<<YAML
project_name: test-project
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
  redis:
    enabled: true
    type: redis
    version: '7-alpine'
    port: 6379
volumes:
  persist: []
YAML;

    file_put_contents($this->tempDir . '/.seaman/seaman.yaml', $yaml);

    $this->registry = ServiceRegistry::create();
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

test('registry shows no enabled services when none are enabled', function () {
    /** @var string $tempDir */
    $tempDir = $this->tempDir;

    // Create config with no enabled services
    $yaml = <<<YAML
project_name: test-project
version: '1.0'
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

    file_put_contents($tempDir . '/.seaman/seaman.yaml', $yaml);

    /** @var ServiceRegistry $registry */
    $registry = $this->registry;
    /** @var ConfigManager $configManager */
    $configManager = new ConfigManager($tempDir, $registry, new ConfigurationValidator());

    $config = $configManager->load();
    $enabled = $registry->enabled($config);

    expect($enabled)->toBeEmpty();
});

test('registry shows enabled services correctly', function () {
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;
    /** @var ConfigManager $configManager */
    $configManager = $this->configManager;

    $config = $configManager->load();
    $enabled = $registry->enabled($config);

    expect(count($enabled))->toBe(2);

    $names = array_map(fn($service) => $service->getName(), $enabled);
    expect($names)->toContain('mysql');
    expect($names)->toContain('redis');
});

test('removes single service from configuration', function () {
    /** @var ConfigManager $configManager */
    $configManager = $this->configManager;

    $config = $configManager->load();

    // Remove MySQL
    $services = $config->services->remove('mysql');
    $config = new Configuration(
        projectName: $config->projectName,
        version: $config->version,
        php: $config->php,
        services: $services,
        volumes: $config->volumes,
    );

    $configManager->save($config);

    // Verify the service was removed from the config file
    $reloadedConfig = $configManager->load();
    expect($reloadedConfig->services->has('mysql'))->toBeFalse();
    expect($reloadedConfig->services->has('redis'))->toBeTrue();
});

test('removes multiple services from configuration', function () {
    /** @var ConfigManager $configManager */
    $configManager = $this->configManager;

    $config = $configManager->load();

    // Remove MySQL
    $services = $config->services->remove('mysql');
    // Remove Redis
    $services = $services->remove('redis');

    $config = new Configuration(
        projectName: $config->projectName,
        version: $config->version,
        php: $config->php,
        services: $services,
        volumes: $config->volumes,
    );

    $configManager->save($config);

    // Verify both services were removed
    $reloadedConfig = $configManager->load();
    expect($reloadedConfig->services->has('mysql'))->toBeFalse();
    expect($reloadedConfig->services->has('redis'))->toBeFalse();
});

test('regenerates .env file after removing services', function () {
    /** @var ConfigManager $configManager */
    $configManager = $this->configManager;
    /** @var string $tempDir */
    $tempDir = $this->tempDir;

    $config = $configManager->load();

    // Remove MySQL
    $services = $config->services->remove('mysql');
    $config = new Configuration(
        projectName: $config->projectName,
        version: $config->version,
        php: $config->php,
        services: $services,
        volumes: $config->volumes,
    );

    $configManager->save($config);

    // Verify .env file still exists but doesn't contain mysql
    expect(file_exists($tempDir . '/.env'))->toBeTrue();

    $envContent = file_get_contents($tempDir . '/.env');
    expect($envContent)->not->toContain('DB_PORT=');
    expect($envContent)->toContain('REDIS_PORT=');
});

test('service collection handles removal of non-existent service', function () {
    /** @var ConfigManager $configManager */
    $configManager = $this->configManager;

    $config = $configManager->load();

    // Try to remove a service that doesn't exist
    $services = $config->services->remove('nonexistent');
    $config = new Configuration(
        projectName: $config->projectName,
        version: $config->version,
        php: $config->php,
        services: $services,
        volumes: $config->volumes,
    );

    $configManager->save($config);

    // Verify original services are still there
    $reloadedConfig = $configManager->load();
    expect($reloadedConfig->services->has('mysql'))->toBeTrue();
    expect($reloadedConfig->services->has('redis'))->toBeTrue();
});
