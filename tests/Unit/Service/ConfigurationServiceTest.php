<?php

declare(strict_types=1);

// ABOUTME: Unit tests for ConfigurationService.
// ABOUTME: Validates service configuration loading and saving.

namespace Seaman\Tests\Unit\Service;

use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Service\ConfigurationService;

test('ConfigurationService loads current config for service', function () {
    $service = new ConfigurationService();

    $config = [
        'services' => [
            'mysql' => [
                'enabled' => true,
                'config' => [
                    'version' => '8.0',
                    'port' => 3306,
                ],
            ],
        ],
    ];

    $result = $service->extractServiceConfig('mysql', $config);

    expect($result)->toBe([
        'version' => '8.0',
        'port' => 3306,
    ]);
});

test('ConfigurationService returns empty array if no config exists', function () {
    $service = new ConfigurationService();

    $config = [
        'services' => [
            'mysql' => [
                'enabled' => true,
            ],
        ],
    ];

    $result = $service->extractServiceConfig('mysql', $config);

    expect($result)->toBe([]);
});

test('ConfigurationService merges new config with existing', function () {
    $service = new ConfigurationService();

    $existingConfig = [
        'services' => [
            'mysql' => [
                'enabled' => true,
                'config' => [
                    'version' => '8.0',
                ],
            ],
        ],
    ];

    $newServiceConfig = [
        'version' => '8.4',
        'port' => 3307,
    ];

    $result = $service->mergeConfig($existingConfig, 'mysql', $newServiceConfig);

    /** @var array<string, mixed> $services */
    $services = $result['services'];
    /** @var array<string, mixed> $mysql */
    $mysql = $services['mysql'];
    /** @var array<string, mixed> $config */
    $config = $mysql['config'];

    expect($config)->toBe([
        'version' => '8.4',
        'port' => 3307,
    ]);
});

test('ConfigurationService renders text field config', function () {
    $service = new ConfigurationService();

    $schema = ConfigSchema::create()
        ->string('version', default: '8.0')
            ->label('MySQL Version')
            ->description('Docker image tag');

    $fields = $schema->getFields();
    $config = $service->buildPromptConfig($fields['version'], ['version' => '8.4']);

    expect($config['type'])->toBe('text')
        ->and($config['label'])->toBe('MySQL Version')
        ->and($config['hint'])->toBe('Docker image tag')
        ->and($config['default'])->toBe('8.4');
});

test('ConfigurationService renders password field for secret', function () {
    $service = new ConfigurationService();

    $schema = ConfigSchema::create()
        ->string('password', default: 'secret')
            ->label('Root Password')
            ->secret();

    $fields = $schema->getFields();
    $config = $service->buildPromptConfig($fields['password'], []);

    expect($config['type'])->toBe('password')
        ->and($config['label'])->toBe('Root Password');
});

test('ConfigurationService renders select for enum field', function () {
    $service = new ConfigurationService();

    $schema = ConfigSchema::create()
        ->string('log_level', default: 'info')
            ->enum(['debug', 'info', 'warn', 'error'])
            ->label('Log Level');

    $fields = $schema->getFields();
    $config = $service->buildPromptConfig($fields['log_level'], []);

    expect($config['type'])->toBe('select')
        ->and($config['options'])->toBe(['debug', 'info', 'warn', 'error'])
        ->and($config['default'])->toBe('info');
});

test('ConfigurationService renders confirm for boolean field', function () {
    $service = new ConfigurationService();

    $schema = ConfigSchema::create()
        ->boolean('metrics', default: false)
            ->label('Enable Metrics');

    $fields = $schema->getFields();
    $config = $service->buildPromptConfig($fields['metrics'], ['metrics' => true]);

    expect($config['type'])->toBe('confirm')
        ->and($config['label'])->toBe('Enable Metrics')
        ->and($config['default'])->toBeTrue();
});

test('ConfigurationService renders text for integer field', function () {
    $service = new ConfigurationService();

    $schema = ConfigSchema::create()
        ->integer('port', default: 3306)
            ->label('Port');

    $fields = $schema->getFields();
    $config = $service->buildPromptConfig($fields['port'], []);

    expect($config['type'])->toBe('text')
        ->and($config['label'])->toBe('Port')
        ->and($config['default'])->toBe('3306');
});
