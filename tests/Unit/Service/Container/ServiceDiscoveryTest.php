<?php

declare(strict_types=1);

// ABOUTME: Tests for ServiceDiscovery autodiscovery mechanism.
// ABOUTME: Validates filesystem scanning and service instantiation.

namespace Seaman\Tests\Unit\Service\Container;

use Seaman\Service\Container\ServiceDiscovery;
use Seaman\Service\Container\ServiceInterface;

test('discovers all service classes in directory', function () {
    $serviceDir = __DIR__ . '/../../../../src/Service/Container';
    $discovery = new ServiceDiscovery($serviceDir);

    $services = $discovery->discover();

    // Should find all concrete service implementations
    // As of this test: MySQL, PostgreSQL, MariaDB, Redis, Memcached, MongoDB,
    // RabbitMQ, Elasticsearch, Minio, Dozzle, Mailpit, Kafka, Sqlite = 13 services
    expect($services)->toBeArray()
        ->and($services)->toHaveCount(13)
        ->and($services)->each->toBeInstanceOf(ServiceInterface::class);

    // Verify some known services are discovered
    $serviceNames = array_map(fn($s) => $s->getName(), $services);
    expect($serviceNames)->toContain('mysql')
        ->and($serviceNames)->toContain('redis')
        ->and($serviceNames)->toContain('postgresql')
        ->and($serviceNames)->toContain('kafka')
        ->and($serviceNames)->toContain('sqlite');
});

test('returns empty array for non-existent directory', function () {
    $discovery = new ServiceDiscovery('/non/existent/path');

    $services = $discovery->discover();

    expect($services)->toBeArray()
        ->and($services)->toBeEmpty();
});

test('skips abstract classes and interfaces', function () {
    $serviceDir = __DIR__ . '/../../../../src/Service/Container';
    $discovery = new ServiceDiscovery($serviceDir);

    $services = $discovery->discover();

    // Should not include AbstractService or ServiceInterface
    $serviceClasses = array_map(fn($s) => get_class($s), $services);
    expect($serviceClasses)->not->toContain('Seaman\Service\Container\AbstractService')
        ->and($serviceClasses)->not->toContain('Seaman\Service\Container\ServiceInterface');
});
