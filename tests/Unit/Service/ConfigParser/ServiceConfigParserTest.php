<?php

declare(strict_types=1);

use Seaman\Enum\Service;
use Seaman\Service\ConfigParser\ServiceConfigParser;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;

beforeEach(function () {
    $this->parser = new ServiceConfigParser();
});

test('parses service configuration', function () {
    $data = [
        'services' => [
            'mysql' => [
                'enabled' => true,
                'type' => 'mysql',
                'version' => '8.0',
                'port' => 3306,
            ],
            'redis' => [
                'enabled' => true,
                'type' => 'redis',
                'version' => '7-alpine',
                'port' => 6379,
            ],
        ],
    ];

    $result = $this->parser->parse($data);

    expect($result)->toBeInstanceOf(ServiceCollection::class);
    expect($result->has('mysql'))->toBeTrue();
    expect($result->has('redis'))->toBeTrue();
    expect($result->get('mysql')->type)->toBe(Service::MySQL);
    expect($result->get('mysql')->version)->toBe('8.0');
});

test('parses service with additional ports', function () {
    $data = [
        'services' => [
            'rabbitmq' => [
                'enabled' => true,
                'type' => 'rabbitmq',
                'version' => '3-management',
                'port' => 5672,
                'additional_ports' => [15672],
            ],
        ],
    ];

    $result = $this->parser->parse($data);

    expect($result->get('rabbitmq')->additionalPorts)->toBe([15672]);
});

test('parses service with environment variables', function () {
    $data = [
        'services' => [
            'mysql' => [
                'enabled' => true,
                'type' => 'mysql',
                'version' => '8.0',
                'port' => 3306,
                'environment' => [
                    'MYSQL_DATABASE' => 'testdb',
                    'MYSQL_USER' => 'testuser',
                ],
            ],
        ],
    ];

    $result = $this->parser->parse($data);

    expect($result->get('mysql')->environmentVariables)->toBe([
        'MYSQL_DATABASE' => 'testdb',
        'MYSQL_USER' => 'testuser',
    ]);
});

test('throws exception for invalid services configuration', function () {
    $data = ['services' => 'invalid'];

    $this->parser->parse($data);
})->throws(RuntimeException::class, 'Invalid services configuration');

test('skips invalid service entries', function () {
    $data = [
        'services' => [
            'mysql' => [
                'enabled' => true,
                'type' => 'mysql',
                'version' => '8.0',
                'port' => 3306,
            ],
            123 => ['invalid' => 'entry'],
            'invalid' => 'not-an-array',
        ],
    ];

    $result = $this->parser->parse($data);

    expect(count($result->all()))->toBe(1);
    expect($result->has('mysql'))->toBeTrue();
});

test('merges services with base configuration', function () {
    $baseServices = [
        'mysql' => new ServiceConfig(
            name: 'mysql',
            enabled: true,
            type: Service::MySQL,
            version: '8.0',
            port: 3306,
            additionalPorts: [],
            environmentVariables: [],
        ),
    ];

    $overrides = [
        'services' => [
            'redis' => [
                'enabled' => true,
                'type' => 'redis',
                'version' => '7-alpine',
                'port' => 6379,
            ],
        ],
    ];

    $result = $this->parser->merge($overrides, $baseServices);

    expect($result->has('mysql'))->toBeTrue();
    expect($result->has('redis'))->toBeTrue();
});

test('merge overrides existing service', function () {
    $baseServices = [
        'mysql' => new ServiceConfig(
            name: 'mysql',
            enabled: true,
            type: Service::MySQL,
            version: '8.0',
            port: 3306,
            additionalPorts: [],
            environmentVariables: [],
        ),
    ];

    $overrides = [
        'services' => [
            'mysql' => [
                'enabled' => false,
                'type' => 'mysql',
                'version' => '8.1',
                'port' => 3307,
            ],
        ],
    ];

    $result = $this->parser->merge($overrides, $baseServices);

    expect($result->get('mysql')->enabled)->toBeFalse();
    expect($result->get('mysql')->version)->toBe('8.1');
    expect($result->get('mysql')->port)->toBe(3307);
});
