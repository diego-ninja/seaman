<?php

// ABOUTME: Tests for PluginServiceAdapter.
// ABOUTME: Validates adapter delegates to ServiceDefinition correctly.

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin;

use Seaman\Enum\Service;
use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\PluginServiceAdapter;
use Seaman\Plugin\ServiceDefinition;
use Seaman\ValueObject\HealthCheck;
use Seaman\ValueObject\ServiceConfig;

test('adapter delegates getName to ServiceDefinition', function (): void {
    $definition = new ServiceDefinition(
        name: 'test-service',
        template: '/path/to/template.yaml',
    );

    $adapter = new PluginServiceAdapter($definition);

    expect($adapter->getName())->toBe('test-service');
});

test('adapter uses displayName from ServiceDefinition if provided', function (): void {
    $definition = new ServiceDefinition(
        name: 'test-service',
        template: '/path/to/template.yaml',
        displayName: 'Test Service Display',
    );

    $adapter = new PluginServiceAdapter($definition);

    expect($adapter->getDisplayName())->toBe('Test Service Display');
});

test('adapter generates displayName from name if not provided', function (): void {
    $definition = new ServiceDefinition(
        name: 'my-custom-service',
        template: '/path/to/template.yaml',
    );

    $adapter = new PluginServiceAdapter($definition);

    expect($adapter->getDisplayName())->toBe('My Custom Service');
});

test('adapter delegates description to ServiceDefinition', function (): void {
    $definition = new ServiceDefinition(
        name: 'test-service',
        template: '/path/to/template.yaml',
        description: 'Custom description',
    );

    $adapter = new PluginServiceAdapter($definition);

    expect($adapter->getDescription())->toBe('Custom description');
});

test('adapter delegates icon to ServiceDefinition', function (): void {
    $definition = new ServiceDefinition(
        name: 'test-service',
        template: '/path/to/template.yaml',
        icon: '🚀',
    );

    $adapter = new PluginServiceAdapter($definition);

    expect($adapter->getIcon())->toBe('🚀');
});

test('adapter delegates dependencies to ServiceDefinition', function (): void {
    $definition = new ServiceDefinition(
        name: 'test-service',
        template: '/path/to/template.yaml',
        dependencies: ['redis', 'postgresql'],
    );

    $adapter = new PluginServiceAdapter($definition);

    expect($adapter->getDependencies())->toBe(['redis', 'postgresql']);
});

test('adapter returns Custom type', function (): void {
    $definition = new ServiceDefinition(
        name: 'test-service',
        template: '/path/to/template.yaml',
    );

    $adapter = new PluginServiceAdapter($definition);

    expect($adapter->getType())->toBe(Service::Custom);
});

test('adapter delegates ports to ServiceDefinition', function (): void {
    $definition = new ServiceDefinition(
        name: 'test-service',
        template: '/path/to/template.yaml',
        ports: [8080, 9090],
    );

    $adapter = new PluginServiceAdapter($definition);

    expect($adapter->getRequiredPorts())->toBe([8080, 9090]);
});

test('adapter delegates internalPorts to ServiceDefinition', function (): void {
    $definition = new ServiceDefinition(
        name: 'test-service',
        template: '/path/to/template.yaml',
        internalPorts: [3000, 4000],
    );

    $adapter = new PluginServiceAdapter($definition);

    expect($adapter->getInternalPorts())->toBe([3000, 4000]);
});

test('adapter delegates healthCheck to ServiceDefinition', function (): void {
    $healthCheck = new HealthCheck(
        test: ['CMD', 'curl', '-f', 'http://localhost:8080/health'],
        interval: '30s',
        timeout: '10s',
        retries: 3,
    );

    $definition = new ServiceDefinition(
        name: 'test-service',
        template: '/path/to/template.yaml',
        healthCheck: $healthCheck,
    );

    $adapter = new PluginServiceAdapter($definition);

    expect($adapter->getHealthCheck())->toBe($healthCheck);
});

test('adapter returns null healthCheck when not defined', function (): void {
    $definition = new ServiceDefinition(
        name: 'test-service',
        template: '/path/to/template.yaml',
    );

    $adapter = new PluginServiceAdapter($definition);

    expect($adapter->getHealthCheck())->toBeNull();
});

test('adapter creates default config with definition values', function (): void {
    $definition = new ServiceDefinition(
        name: 'test-service',
        template: '/path/to/template.yaml',
        defaultConfig: [
            'version' => '1.2.3',
            'environment' => ['FOO' => 'bar'],
        ],
        ports: [8080],
    );

    $adapter = new PluginServiceAdapter($definition);
    $config = $adapter->getDefaultConfig();

    expect($config->name)->toBe('test-service')
        ->and($config->enabled)->toBe(true)
        ->and($config->type)->toBe(Service::Custom)
        ->and($config->port)->toBe(8080)
        ->and($config->version)->toBe('1.2.3')
        ->and($config->environmentVariables)->toBe(['FOO' => 'bar']);
});

test('adapter uses first port as default port', function (): void {
    $definition = new ServiceDefinition(
        name: 'test-service',
        template: '/path/to/template.yaml',
        ports: [9000, 9001],
    );

    $adapter = new PluginServiceAdapter($definition);
    $config = $adapter->getDefaultConfig();

    expect($config->port)->toBe(9000)
        ->and($config->additionalPorts)->toBe([9001]);
});

test('adapter uses 0 as default port when no ports defined', function (): void {
    $definition = new ServiceDefinition(
        name: 'test-service',
        template: '/path/to/template.yaml',
    );

    $adapter = new PluginServiceAdapter($definition);
    $config = $adapter->getDefaultConfig();

    expect($config->port)->toBe(0)
        ->and($config->additionalPorts)->toBe([]);
});

test('adapter uses latest as default version when not specified', function (): void {
    $definition = new ServiceDefinition(
        name: 'test-service',
        template: '/path/to/template.yaml',
    );

    $adapter = new PluginServiceAdapter($definition);
    $config = $adapter->getDefaultConfig();

    expect($config->version)->toBe('latest');
});

test('adapter generates basic compose config from template path', function (): void {
    $definition = new ServiceDefinition(
        name: 'test-service',
        template: '/path/to/template.yaml',
    );

    $adapter = new PluginServiceAdapter($definition);
    $config = new ServiceConfig(
        name: 'test-service',
        enabled: true,
        type: Service::Custom,
        version: '1.0.0',
        port: 8080,
        additionalPorts: [],
        environmentVariables: [],
    );

    $composeConfig = $adapter->generateComposeConfig($config);

    expect($composeConfig)->toHaveKey('__template_path')
        ->and($composeConfig['__template_path'])->toBe('/path/to/template.yaml');
});

test('adapter returns env variables from config', function (): void {
    $definition = new ServiceDefinition(
        name: 'test-service',
        template: '/path/to/template.yaml',
    );

    $adapter = new PluginServiceAdapter($definition);
    $config = new ServiceConfig(
        name: 'test-service',
        enabled: true,
        type: Service::Custom,
        version: '1.0.0',
        port: 8080,
        additionalPorts: [],
        environmentVariables: ['KEY' => 'value'],
    );

    expect($adapter->getEnvVariables($config))->toBe(['KEY' => 'value']);
});

test('adapter returns inspect info with version', function (): void {
    $definition = new ServiceDefinition(
        name: 'test-service',
        template: '/path/to/template.yaml',
    );

    $adapter = new PluginServiceAdapter($definition);
    $config = new ServiceConfig(
        name: 'test-service',
        enabled: true,
        type: Service::Custom,
        version: '2.3.4',
        port: 8080,
        additionalPorts: [],
        environmentVariables: [],
    );

    expect($adapter->getInspectInfo($config))->toBe('v2.3.4');
});
