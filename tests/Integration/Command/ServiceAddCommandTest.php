<?php

declare(strict_types=1);

// ABOUTME: Tests for ServiceAddCommand.
// ABOUTME: Validates interactive service addition functionality.

namespace Seaman\Tests\Integration\Command;

use Seaman\Service\ConfigManager;
use Seaman\Service\ConfigurationValidator;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\ServiceConfig;

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

test('registry shows all services as available when none enabled', function () {
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;
    /** @var ConfigManager $configManager */
    $configManager = $this->configManager;

    $config = $configManager->load();
    $available = $registry->disabled($config);

    // With bundled plugins, we have 18 services (17 plugins + 1 Traefik)
    expect(count($available))->toBeGreaterThanOrEqual(18);

    $names = array_map(fn($service) => $service->getName(), $available);
    expect($names)->toContain('mysql');
    expect($names)->toContain('postgresql');
    expect($names)->toContain('redis');
});

test('registry shows enabled services as unavailable', function () {
    /** @var string $tempDir */
    $tempDir = $this->tempDir;

    // Create config with some services enabled
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
  postgresql:
    enabled: true
    type: postgresql
    version: '16'
    port: 5432
  redis:
    enabled: true
    type: redis
    version: '7-alpine'
    port: 6379
volumes:
  persist: []
YAML;

    file_put_contents($tempDir . '/.seaman/seaman.yaml', $yaml);

    /** @var ServiceRegistry $registry */
    $registry = $this->registry;
    /** @var ConfigManager $configManager */
    $configManager = new ConfigManager($tempDir, $registry, new ConfigurationValidator());

    $config = $configManager->load();
    $disabled = $registry->disabled($config);

    // Enabled services should not appear in disabled list
    $disabledNames = array_map(fn($service) => $service->getName(), $disabled);
    expect($disabledNames)->not->toContain('mysql');
    expect($disabledNames)->not->toContain('postgresql');
    expect($disabledNames)->not->toContain('redis');

    // Other services should still be in the disabled list
    expect($disabledNames)->toContain('mariadb');
    expect($disabledNames)->toContain('mongodb');
});

test('adds single service to configuration', function () {
    /** @var ConfigManager $configManager */
    $configManager = $this->configManager;
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;

    $config = $configManager->load();

    // Manually add service like the command would
    $service = $registry->get(\Seaman\Enum\Service::MySQL);
    $defaultConfig = $service->getDefaultConfig();

    $serviceConfig = new ServiceConfig(
        name: $defaultConfig->name,
        enabled: true,
        type: $defaultConfig->type,
        version: $defaultConfig->version,
        port: $defaultConfig->port,
        additionalPorts: $defaultConfig->additionalPorts,
        environmentVariables: $defaultConfig->environmentVariables,
    );

    $services = $config->services->add('mysql', $serviceConfig);
    $config = new Configuration(
        projectName: $config->projectName,
        version: $config->version,
        php: $config->php,
        services: $services,
        volumes: $config->volumes,
    );

    $configManager->save($config);

    // Verify the service was added to the config file
    $reloadedConfig = $configManager->load();
    expect($reloadedConfig->services->has('mysql'))->toBeTrue();
    expect($reloadedConfig->services->get('mysql')->enabled)->toBeTrue();
    expect($reloadedConfig->services->get('mysql')->type)->toBe(\Seaman\Enum\Service::MySQL);
});

test('adds multiple services to configuration', function () {
    /** @var ConfigManager $configManager */
    $configManager = $this->configManager;
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;

    $config = $configManager->load();

    // Add MySQL
    $mysqlService = $registry->get(\Seaman\Enum\Service::MySQL);
    $mysqlConfig = $mysqlService->getDefaultConfig();
    $services = $config->services->add('mysql', new ServiceConfig(
        name: $mysqlConfig->name,
        enabled: true,
        type: $mysqlConfig->type,
        version: $mysqlConfig->version,
        port: $mysqlConfig->port,
        additionalPorts: $mysqlConfig->additionalPorts,
        environmentVariables: $mysqlConfig->environmentVariables,
    ));

    // Add Redis
    $redisService = $registry->get(\Seaman\Enum\Service::Redis);
    $redisConfig = $redisService->getDefaultConfig();
    $services = $services->add('redis', new ServiceConfig(
        name: $redisConfig->name,
        enabled: true,
        type: $redisConfig->type,
        version: $redisConfig->version,
        port: $redisConfig->port,
        additionalPorts: $redisConfig->additionalPorts,
        environmentVariables: $redisConfig->environmentVariables,
    ));

    $config = new Configuration(
        projectName: $config->projectName,
        version: $config->version,
        php: $config->php,
        services: $services,
        volumes: $config->volumes,
    );

    $configManager->save($config);

    // Verify both services were added
    $reloadedConfig = $configManager->load();
    expect($reloadedConfig->services->has('mysql'))->toBeTrue();
    expect($reloadedConfig->services->has('redis'))->toBeTrue();
});

test('regenerates .env file after adding services', function () {
    /** @var ConfigManager $configManager */
    $configManager = $this->configManager;
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;
    /** @var string $tempDir */
    $tempDir = $this->tempDir;

    $config = $configManager->load();

    // Add MySQL
    $service = $registry->get(\Seaman\Enum\Service::MySQL);
    $defaultConfig = $service->getDefaultConfig();

    $services = $config->services->add('mysql', new ServiceConfig(
        name: $defaultConfig->name,
        enabled: true,
        type: $defaultConfig->type,
        version: $defaultConfig->version,
        port: $defaultConfig->port,
        additionalPorts: $defaultConfig->additionalPorts,
        environmentVariables: $defaultConfig->environmentVariables,
    ));

    $config = new Configuration(
        projectName: $config->projectName,
        version: $config->version,
        php: $config->php,
        services: $services,
        volumes: $config->volumes,
    );

    $configManager->save($config);

    // Verify .env file was created
    expect(file_exists($tempDir . '/.env'))->toBeTrue();

    $envContent = file_get_contents($tempDir . '/.env');
    expect($envContent)->toContain('DB_PORT=');
});
