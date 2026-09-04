<?php

declare(strict_types=1);

// ABOUTME: Unit tests for ConfigurationService.
// ABOUTME: Validates service configuration loading and saving.

namespace Seaman\Tests\Unit\Service;

use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Service\ConfigParser\ServiceConfigParser;
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

test('ConfigurationService materializes configured values for the runtime', function () {
    $service = new ConfigurationService();

    $result = $service->mergeConfig([
        'services' => [
            'postgresql' => [
                'enabled' => true,
                'type' => 'postgresql',
                'version' => '16',
                'port' => 5432,
                'environment' => ['CUSTOM_OPTION' => 'preserved'],
            ],
        ],
    ], 'postgresql', [
        'version' => '17',
        'port' => 55432,
        'database' => 'application',
        'user' => 'developer',
        'password' => 'secret',
    ]);

    /** @var array<string, array<string, mixed>> $services */
    $services = $result['services'];
    $postgresql = $services['postgresql'];

    expect($postgresql['version'])->toBe('17')
        ->and($postgresql['port'])->toBe(55432)
        ->and($postgresql['environment'])->toMatchArray([
            'CUSTOM_OPTION' => 'preserved',
            'DB_NAME' => 'application',
            'DB_USER' => 'developer',
            'DB_PASSWORD' => 'secret',
            'POSTGRES_DB' => 'application',
            'POSTGRES_USER' => 'developer',
            'POSTGRES_PASSWORD' => 'secret',
        ]);
});

test('ConfigurationService normalizes Compose list environment and replaces managed variables', function () {
    $service = new ConfigurationService();

    $result = $service->mergeConfig([
        'services' => [
            'postgresql' => [
                'environment' => [
                    'CUSTOM_OPTION=preserved',
                    'INHERITED_FROM_HOST',
                    'POSTGRES_DB=legacy',
                    'DB_NAME=legacy',
                    'POSTGRES_DB=duplicate',
                ],
            ],
        ],
    ], 'postgresql', [
        'database' => 'application',
    ]);

    /** @var array<string, array<string, mixed>> $services */
    $services = $result['services'];

    expect($services['postgresql']['environment'])->toBe([
        'CUSTOM_OPTION' => 'preserved',
        'INHERITED_FROM_HOST' => null,
        'POSTGRES_DB' => 'application',
        'DB_NAME' => 'application',
    ]);
});

test('ServiceConfigParser reloads canonical environment without losing null values', function () {
    $services = (new ServiceConfigParser())->parse([
        'services' => [
            'postgresql' => [
                'enabled' => true,
                'type' => 'postgresql',
                'environment' => [
                    'CUSTOM_OPTION' => 'preserved',
                    'INHERITED_FROM_HOST' => null,
                    'POSTGRES_DB' => 'application',
                ],
            ],
        ],
    ]);

    expect($services->get('postgresql')->environmentVariables)->toBe([
        'CUSTOM_OPTION' => 'preserved',
        'INHERITED_FROM_HOST' => null,
        'POSTGRES_DB' => 'application',
    ]);
});

test('ConfigurationService maps additional ports and plugin-specific variables', function () {
    $service = new ConfigurationService();

    $result = $service->mergeConfig([
        'services' => ['soketi' => ['enabled' => true, 'type' => 'soketi']],
    ], 'soketi', [
        'version' => '1.6',
        'port' => 6002,
        'metrics_port' => 9602,
        'app_id' => 'configured-id',
        'app_key' => 'configured-key',
        'app_secret' => 'configured-secret',
    ]);

    /** @var array<string, array<string, mixed>> $services */
    $services = $result['services'];
    $soketi = $services['soketi'];

    expect($soketi['additional_ports'])->toBe([9602])
        ->and($soketi['environment'])->toMatchArray([
            'PUSHER_APP_ID' => 'configured-id',
            'PUSHER_APP_KEY' => 'configured-key',
            'PUSHER_APP_SECRET' => 'configured-secret',
        ]);
});

test('ConfigurationService hydrates typed legacy values without overriding modern config', function () {
    $service = new ConfigurationService();
    $schema = ConfigSchema::create()
        ->string('version', default: '3-management')
        ->integer('port', default: 5672)
        ->integer('management_port', default: 15672)
        ->string('user', default: 'seaman')
        ->string('password', default: 'seaman')->secret()
        ->boolean('tls', default: false);

    $result = $service->hydrateServiceConfig('rabbitmq', $schema, [
        'services' => [
            'rabbitmq' => [
                'version' => '4.0-management',
                'port' => 5673,
                'additional_ports' => [15673],
                'environment' => [
                    'RABBITMQ_DEFAULT_USER' => 'legacy-user',
                    'RABBITMQ_DEFAULT_PASS' => 'legacy-secret',
                    'RABBITMQ_TLS' => 'true',
                ],
                'config' => ['port' => 5674],
            ],
        ],
    ]);

    expect($result)->toMatchArray([
        'version' => '4.0-management',
        'port' => 5674,
        'management_port' => 15673,
        'user' => 'legacy-user',
        'password' => 'legacy-secret',
        'tls' => true,
    ]);
});

test('ConfigurationService preserves legacy Elasticsearch security settings', function () {
    $service = new ConfigurationService();
    $schema = ConfigSchema::create()
        ->boolean('security_enabled', default: false);

    $result = $service->hydrateServiceConfig('elasticsearch', $schema, [
        'services' => [
            'elasticsearch' => [
                'environment' => ['xpack.security.enabled' => 'true'],
            ],
        ],
    ]);

    expect($result['security_enabled'] ?? null)->toBeTrue();
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
