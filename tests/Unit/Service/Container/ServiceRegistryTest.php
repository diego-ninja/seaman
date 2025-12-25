<?php

declare(strict_types=1);

// ABOUTME: Tests for ServiceRegistry.
// ABOUTME: Validates service registration, retrieval, and filtering.

namespace Seaman\Tests\Unit\Service\Container;

use Seaman\Enum\PhpVersion;
use Seaman\Enum\Service;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Service\Container\ServiceInterface;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\HealthCheck;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\VolumeConfig;
use Seaman\ValueObject\XdebugConfig;

/**
 * @property ServiceRegistry $registry
 * @property Configuration $config
 */
beforeEach(function () {
    $this->registry = new ServiceRegistry();

    // Create a configuration with some enabled services
    $this->config = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: new PhpConfig(
            version: PhpVersion::Php84,
            xdebug: new XdebugConfig(
                enabled: false,
                ideKey: 'PHPSTORM',
                clientHost: 'localhost',
            ),
        ),
        services: new ServiceCollection([
            'mysql' => new ServiceConfig(
                name: 'mysql',
                enabled: true,
                type: Service::MySQL,
                version: '8.0',
                port: 3306,
                additionalPorts: [],
                environmentVariables: [],
            ),
            'redis' => new ServiceConfig(
                name: 'redis',
                enabled: false,
                type: Service::Redis,
                version: '7.0',
                port: 6379,
                additionalPorts: [],
                environmentVariables: [],
            ),
        ]),
        volumes: new VolumeConfig(
            persist: ['mysql-data', 'redis-data'],
        ),
    );
});

test('can register a service', function () {
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;

    $service = new class implements ServiceInterface {
        public function getName(): string
        {
            return 'custom';
        }

        public function getDisplayName(): string
        {
            return 'Test Service';
        }

        public function getDescription(): string
        {
            return 'A test service';
        }

        public function getDependencies(): array
        {
            return [];
        }

        public function getDefaultConfig(): ServiceConfig
        {
            return new ServiceConfig(
                name: 'custom',
                enabled: false,
                type: Service::Custom,
                version: '1.0',
                port: 8000,
                additionalPorts: [],
                environmentVariables: [],
            );
        }

        public function generateComposeConfig(ServiceConfig $config): array
        {
            return ['image' => 'test:1.0'];
        }

        public function getRequiredPorts(): array
        {
            return [8000];
        }

        public function getHealthCheck(): ?HealthCheck
        {
            return null;
        }

        public function getEnvVariables(ServiceConfig $config): array
        {
            return [];
        }

        public function getType(): Service
        {
            return Service::Custom;
        }

        public function getIcon(): string
        {
            return 'test';
        }

        public function getInternalPorts(): array
        {
            return $this->getRequiredPorts();
        }

        public function getInspectInfo(ServiceConfig $config): string
        {
            return 'info';
        }

        public function getConfigSchema(): ?ConfigSchema
        {
            return null;
        }
    };

    $registry->register($service);

    expect($registry->get(Service::Custom))->toBe($service);
});

test('throws exception when getting non-existent service', function () {
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;

    $registry->get(Service::Redis);
})->throws(\InvalidArgumentException::class, "Service 'redis' not found");

test('returns all registered services', function () {
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;

    $service1 = new class implements ServiceInterface {
        public function getName(): string
        {
            return 'service1';
        }

        public function getDisplayName(): string
        {
            return 'Service 1';
        }

        public function getDescription(): string
        {
            return 'First service';
        }

        public function getDependencies(): array
        {
            return [];
        }

        public function getDefaultConfig(): ServiceConfig
        {
            return new ServiceConfig(
                name: 'service1',
                enabled: false,
                type: Service::Custom,
                version: '1.0',
                port: 8001,
                additionalPorts: [],
                environmentVariables: [],
            );
        }

        public function generateComposeConfig(ServiceConfig $config): array
        {
            return ['image' => 'service1:1.0'];
        }

        public function getRequiredPorts(): array
        {
            return [8001];
        }

        public function getHealthCheck(): ?HealthCheck
        {
            return null;
        }

        public function getEnvVariables(ServiceConfig $config): array
        {
            return [];
        }

        public function getType(): Service
        {
            return Service::Custom;
        }

        public function getIcon(): string
        {
            return '⚙️';
        }

        public function getInternalPorts(): array
        {
            return $this->getRequiredPorts();
        }

        public function getInspectInfo(ServiceConfig $config): string
        {
            return 'info';
        }

        public function getConfigSchema(): ?ConfigSchema
        {
            return null;
        }
    };

    $service2 = new class implements ServiceInterface {
        public function getName(): string
        {
            return 'service2';
        }

        public function getDisplayName(): string
        {
            return 'Service 2';
        }

        public function getDescription(): string
        {
            return 'Second service';
        }

        public function getDependencies(): array
        {
            return [];
        }

        public function getDefaultConfig(): ServiceConfig
        {
            return new ServiceConfig(
                name: 'service2',
                enabled: false,
                type: Service::Redis,
                version: '1.0',
                port: 8002,
                additionalPorts: [],
                environmentVariables: [],
            );
        }

        public function generateComposeConfig(ServiceConfig $config): array
        {
            return ['image' => 'service2:1.0'];
        }

        public function getRequiredPorts(): array
        {
            return [8002];
        }

        public function getHealthCheck(): ?HealthCheck
        {
            return null;
        }

        public function getEnvVariables(ServiceConfig $config): array
        {
            return [];
        }

        public function getType(): Service
        {
            return Service::Redis;
        }

        public function getIcon(): string
        {
            return 'test';
        }

        public function getInternalPorts(): array
        {
            return $this->getRequiredPorts();
        }

        public function getInspectInfo(ServiceConfig $config): string
        {
            return 'info';
        }

        public function getConfigSchema(): ?ConfigSchema
        {
            return null;
        }

    };

    $registry->register($service1);
    $registry->register($service2);

    $all = $registry->all();

    expect($all)->toHaveCount(2)
        ->and($all)->toHaveKeys(['service1', 'service2'])
        ->and($all['service1'])->toBe($service1)
        ->and($all['service2'])->toBe($service2);
});

test('returns only enabled services', function () {
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;
    /** @var Configuration $config */
    $config = $this->config;

    // Register services matching the config
    $mysqlService = new class implements ServiceInterface {
        public function getName(): string
        {
            return 'mysql';
        }

        public function getDisplayName(): string
        {
            return 'MySQL';
        }

        public function getDescription(): string
        {
            return 'MySQL database';
        }

        public function getDependencies(): array
        {
            return [];
        }

        public function getDefaultConfig(): ServiceConfig
        {
            return new ServiceConfig(
                name: 'mysql',
                enabled: false,
                type: Service::MySQL,
                version: '8.0',
                port: 3306,
                additionalPorts: [],
                environmentVariables: [],
            );
        }

        public function generateComposeConfig(ServiceConfig $config): array
        {
            return ['image' => 'mysql:8.0'];
        }

        public function getRequiredPorts(): array
        {
            return [3306];
        }

        public function getHealthCheck(): ?HealthCheck
        {
            return null;
        }

        public function getEnvVariables(ServiceConfig $config): array
        {
            return [];
        }

        public function getType(): Service
        {
            return Service::MySQL;
        }

        public function getIcon(): string
        {
            return '🐬';
        }

        public function getInternalPorts(): array
        {
            return $this->getRequiredPorts();
        }

        public function getInspectInfo(ServiceConfig $config): string
        {
            return 'info';
        }

        public function getConfigSchema(): ?ConfigSchema
        {
            return null;
        }

    };

    $redisService = new class implements ServiceInterface {
        public function getName(): string
        {
            return 'redis';
        }

        public function getDisplayName(): string
        {
            return 'Redis';
        }

        public function getDescription(): string
        {
            return 'Redis cache';
        }

        public function getDependencies(): array
        {
            return [];
        }

        public function getDefaultConfig(): ServiceConfig
        {
            return new ServiceConfig(
                name: 'redis',
                enabled: false,
                type: Service::Redis,
                version: '7.0',
                port: 6379,
                additionalPorts: [],
                environmentVariables: [],
            );
        }

        public function generateComposeConfig(ServiceConfig $config): array
        {
            return ['image' => 'redis:7.0'];
        }

        public function getRequiredPorts(): array
        {
            return [6379];
        }

        public function getHealthCheck(): ?HealthCheck
        {
            return null;
        }

        public function getEnvVariables(ServiceConfig $config): array
        {
            return [];
        }

        public function getType(): Service
        {
            return Service::Redis;
        }

        public function getIcon(): string
        {
            return '🧵';
        }

        public function getInternalPorts(): array
        {
            return $this->getRequiredPorts();
        }

        public function getInspectInfo(ServiceConfig $config): string
        {
            return 'info';
        }

        public function getConfigSchema(): ?ConfigSchema
        {
            return null;
        }
    };

    $registry->register($mysqlService);
    $registry->register($redisService);

    $enabled = $registry->enabled($config);

    expect($enabled)->toHaveCount(1)
        ->and($enabled[0]->getName())->toBe('mysql');
});

test('returns only available services', function () {
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;
    /** @var Configuration $config */
    $config = $this->config;

    // Register services matching the config
    $mysqlService = new class implements ServiceInterface {
        public function getName(): string
        {
            return 'mysql';
        }

        public function getDisplayName(): string
        {
            return 'MySQL';
        }

        public function getDescription(): string
        {
            return 'MySQL database';
        }

        public function getDependencies(): array
        {
            return [];
        }

        public function getDefaultConfig(): ServiceConfig
        {
            return new ServiceConfig(
                name: 'mysql',
                enabled: false,
                type: Service::MySQL,
                version: '8.0',
                port: 3306,
                additionalPorts: [],
                environmentVariables: [],
            );
        }

        public function generateComposeConfig(ServiceConfig $config): array
        {
            return ['image' => 'mysql:8.0'];
        }

        public function getRequiredPorts(): array
        {
            return [3306];
        }

        public function getHealthCheck(): ?HealthCheck
        {
            return null;
        }

        public function getEnvVariables(ServiceConfig $config): array
        {
            return [];
        }

        public function getType(): Service
        {
            return Service::MySQL;
        }

        public function getIcon(): string
        {
            return '🐬';
        }

        public function getInternalPorts(): array
        {
            return $this->getRequiredPorts();
        }

        public function getInspectInfo(ServiceConfig $config): string
        {
            return 'info';
        }

        public function getConfigSchema(): ?ConfigSchema
        {
            return null;
        }
    };

    $redisService = new class implements ServiceInterface {
        public function getName(): string
        {
            return 'redis';
        }

        public function getDisplayName(): string
        {
            return 'Redis';
        }

        public function getDescription(): string
        {
            return 'Redis cache';
        }

        public function getDependencies(): array
        {
            return [];
        }

        public function getDefaultConfig(): ServiceConfig
        {
            return new ServiceConfig(
                name: 'redis',
                enabled: false,
                type: Service::Redis,
                version: '7.0',
                port: 6379,
                additionalPorts: [],
                environmentVariables: [],
            );
        }

        public function generateComposeConfig(ServiceConfig $config): array
        {
            return ['image' => 'redis:7.0'];
        }

        public function getRequiredPorts(): array
        {
            return [6379];
        }

        public function getHealthCheck(): ?HealthCheck
        {
            return null;
        }

        public function getEnvVariables(ServiceConfig $config): array
        {
            return [];
        }

        public function getType(): Service
        {
            return Service::Redis;
        }

        public function getIcon(): string
        {
            return '🧵';
        }

        public function getInternalPorts(): array
        {
            return $this->getRequiredPorts();
        }

        public function getInspectInfo(ServiceConfig $config): string
        {
            return 'info';
        }

        public function getConfigSchema(): ?ConfigSchema
        {
            return null;
        }
    };

    $registry->register($mysqlService);
    $registry->register($redisService);

    $available = $registry->disabled($config);

    expect($available)->toHaveCount(1)
        ->and($available[0]->getName())->toBe('redis');
});

test('replaces service with same name on re-registration', function () {
    /** @var ServiceRegistry $registry */
    $registry = $this->registry;

    $service1 = new class implements ServiceInterface {
        public function getName(): string
        {
            return 'custom';
        }

        public function getDisplayName(): string
        {
            return 'Test V1';
        }

        public function getDescription(): string
        {
            return 'First version';
        }

        public function getDependencies(): array
        {
            return [];
        }

        public function getDefaultConfig(): ServiceConfig
        {
            return new ServiceConfig(
                name: 'custom',
                enabled: false,
                type: Service::Custom,
                version: '1.0',
                port: 8000,
                additionalPorts: [],
                environmentVariables: [],
            );
        }

        public function generateComposeConfig(ServiceConfig $config): array
        {
            return ['image' => 'test:1.0'];
        }

        public function getRequiredPorts(): array
        {
            return [8000];
        }

        public function getHealthCheck(): ?HealthCheck
        {
            return null;
        }

        public function getEnvVariables(ServiceConfig $config): array
        {
            return [];
        }

        public function getType(): Service
        {
            return Service::Custom;
        }

        public function getIcon(): string
        {
            return '⚙️';
        }

        public function getInternalPorts(): array
        {
            return $this->getRequiredPorts();
        }

        public function getInspectInfo(ServiceConfig $config): string
        {
            return 'info';
        }

        public function getConfigSchema(): ?ConfigSchema
        {
            return null;
        }
    };

    $service2 = new class implements ServiceInterface {
        public function getName(): string
        {
            return 'custom';
        }

        public function getDisplayName(): string
        {
            return 'Test V2';
        }

        public function getDescription(): string
        {
            return 'Second version';
        }

        public function getDependencies(): array
        {
            return [];
        }

        public function getDefaultConfig(): ServiceConfig
        {
            return new ServiceConfig(
                name: 'custom',
                enabled: false,
                type: Service::Custom,
                version: '2.0',
                port: 8000,
                additionalPorts: [],
                environmentVariables: [],
            );
        }

        public function generateComposeConfig(ServiceConfig $config): array
        {
            return ['image' => 'test:2.0'];
        }

        public function getRequiredPorts(): array
        {
            return [8000];
        }

        public function getHealthCheck(): ?HealthCheck
        {
            return null;
        }

        public function getEnvVariables(ServiceConfig $config): array
        {
            return [];
        }

        public function getType(): Service
        {
            return Service::Custom;
        }

        public function getIcon(): string
        {
            return '⚙️';
        }

        public function getInternalPorts(): array
        {
            return $this->getRequiredPorts();
        }

        public function getInspectInfo(ServiceConfig $config): string
        {
            return 'info';
        }

        public function getConfigSchema(): ?ConfigSchema
        {
            return null;
        }
    };

    $registry->register($service1);
    $registry->register($service2);

    expect($registry->all())->toHaveCount(1)
        ->and($registry->get(Service::Custom))->toBe($service2)
        ->and($registry->get(Service::Custom)->getDisplayName())->toBe('Test V2');
});

test('registerPluginServices registers all plugin services', function (): void {
    $registry = new ServiceRegistry();

    $plugin1 = new class implements \Seaman\Plugin\PluginInterface {
        public function getName(): string
        {
            return 'test-plugin-1';
        }

        public function getVersion(): string
        {
            return '1.0.0';
        }

        public function getDescription(): string
        {
            return 'Test plugin 1';
        }

        #[\Seaman\Plugin\Attribute\ProvidesService]
        public function provideTestService(): \Seaman\Plugin\ServiceDefinition
        {
            return new \Seaman\Plugin\ServiceDefinition(
                name: 'custom-service-1',
                template: '/path/to/template1.yaml',
                displayName: 'Custom Service 1',
                icon: '🚀',
                ports: [9001],
            );
        }
    };

    $plugin2 = new class implements \Seaman\Plugin\PluginInterface {
        public function getName(): string
        {
            return 'test-plugin-2';
        }

        public function getVersion(): string
        {
            return '1.0.0';
        }

        public function getDescription(): string
        {
            return 'Test plugin 2';
        }

        #[\Seaman\Plugin\Attribute\ProvidesService]
        public function provideAnotherService(): \Seaman\Plugin\ServiceDefinition
        {
            return new \Seaman\Plugin\ServiceDefinition(
                name: 'custom-service-2',
                template: '/path/to/template2.yaml',
                displayName: 'Custom Service 2',
                icon: '🔥',
                ports: [9002],
            );
        }
    };

    $pluginRegistry = new \Seaman\Plugin\PluginRegistry();
    $pluginRegistry->register($plugin1, [], 'test');
    $pluginRegistry->register($plugin2, [], 'test');

    $registry->registerPluginServices($pluginRegistry);

    $allServices = $registry->all();

    expect($allServices)->toHaveKey('custom-service-1')
        ->and($allServices)->toHaveKey('custom-service-2')
        ->and($allServices['custom-service-1']->getName())->toBe('custom-service-1')
        ->and($allServices['custom-service-1']->getDisplayName())->toBe('Custom Service 1')
        ->and($allServices['custom-service-1']->getIcon())->toBe('🚀')
        ->and($allServices['custom-service-1']->getType())->toBe(Service::Custom)
        ->and($allServices['custom-service-2']->getName())->toBe('custom-service-2')
        ->and($allServices['custom-service-2']->getDisplayName())->toBe('Custom Service 2')
        ->and($allServices['custom-service-2']->getIcon())->toBe('🔥')
        ->and($allServices['custom-service-2']->getType())->toBe(Service::Custom);
});
