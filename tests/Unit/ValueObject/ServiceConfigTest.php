<?php

declare(strict_types=1);

// ABOUTME: Tests for ServiceConfig value object.
// ABOUTME: Validates service configuration structure.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\ValueObject\ServiceConfig;

test('creates service config', function () {
    $config = new ServiceConfig(
        name: 'postgresql',
        enabled: true,
        type: 'postgresql',
        version: '16',
        port: 5432,
        additionalPorts: [],
        environmentVariables: ['POSTGRES_PASSWORD' => 'secret'],
    );

    expect($config->name)->toBe('postgresql')
        ->and($config->enabled)->toBeTrue()
        ->and($config->type)->toBe('postgresql')
        ->and($config->version)->toBe('16')
        ->and($config->port)->toBe(5432)
        ->and($config->additionalPorts)->toBe([])
        ->and($config->environmentVariables)->toBe(['POSTGRES_PASSWORD' => 'secret']);
});

test('creates disabled service config', function () {
    $config = new ServiceConfig(
        name: 'elasticsearch',
        enabled: false,
        type: 'elasticsearch',
        version: '8.11',
        port: 9200,
        additionalPorts: [],
        environmentVariables: [],
    );

    expect($config->enabled)->toBeFalse();
});

test('handles multiple additional ports', function () {
    $config = new ServiceConfig(
        name: 'minio',
        enabled: true,
        type: 'minio',
        version: 'latest',
        port: 9000,
        additionalPorts: [9001],
        environmentVariables: [],
    );

    expect($config->additionalPorts)->toBe([9001]);
});

test('getAllPorts returns all ports', function () {
    $config = new ServiceConfig(
        name: 'rabbitmq',
        enabled: true,
        type: 'rabbitmq',
        version: '3',
        port: 5672,
        additionalPorts: [15672],
        environmentVariables: [],
    );

    expect($config->getAllPorts())->toBe([5672, 15672]);
});
