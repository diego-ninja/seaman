<?php

declare(strict_types=1);

// ABOUTME: Tests for search and queue service implementations.
// ABOUTME: Validates Elasticsearch and RabbitMQ services.

namespace Seaman\Tests\Unit\Service\Container;

use Seaman\Service\Container\ElasticsearchService;
use Seaman\Service\Container\RabbitmqService;

test('elasticsearch service has correct configuration', function () {
    $service = new ElasticsearchService();

    expect($service->getName())->toBe('elasticsearch')
        ->and($service->getDisplayName())->toBe('Elasticsearch')
        ->and($service->getDescription())->toBe('Elasticsearch search engine')
        ->and($service->getRequiredPorts())->toBe([9200])
        ->and($service->getDependencies())->toBe([])
        ->and($service->getDefaultConfig()->name)->toBe('elasticsearch')
        ->and($service->getDefaultConfig()->type)->toBe('elasticsearch')
        ->and($service->getDefaultConfig()->version)->toBe('8.11')
        ->and($service->getDefaultConfig()->port)->toBe(9200)
        ->and($service->getDefaultConfig()->enabled)->toBeFalse()
        ->and($service->getDefaultConfig()->additionalPorts)->toBe([])
        ->and($service->getDefaultConfig()->environmentVariables)->toBe([
            'discovery.type' => 'single-node',
            'xpack.security.enabled' => 'false',
        ]);
});

test('elasticsearch service has correct health check', function () {
    $service = new ElasticsearchService();
    $healthCheck = $service->getHealthCheck();

    expect($healthCheck)->not->toBeNull();
    assert($healthCheck !== null);

    expect($healthCheck->test)->toBe(['CMD-SHELL', 'curl -f http://localhost:9200/_cluster/health || exit 1'])
        ->and($healthCheck->interval)->toBe('10s')
        ->and($healthCheck->timeout)->toBe('5s')
        ->and($healthCheck->retries)->toBe(5);
});

test('elasticsearch service generates compose config', function () {
    $service = new ElasticsearchService();
    $config = $service->getDefaultConfig();

    $composeConfig = $service->generateComposeConfig($config);

    expect($composeConfig)->toHaveKey('image')
        ->and($composeConfig)->toHaveKey('environment')
        ->and($composeConfig)->toHaveKey('ports')
        ->and($composeConfig)->toHaveKey('volumes')
        ->and($composeConfig)->toHaveKey('healthcheck')
        ->and($composeConfig['image'])->toBe('elasticsearch:8.11')
        ->and($composeConfig['volumes'])->toBe(['elasticsearch_data:/usr/share/elasticsearch/data']);
});

test('rabbitmq service has correct configuration', function () {
    $service = new RabbitmqService();

    expect($service->getName())->toBe('rabbitmq')
        ->and($service->getDisplayName())->toBe('RabbitMQ')
        ->and($service->getDescription())->toBe('RabbitMQ message queue')
        ->and($service->getRequiredPorts())->toContain(5672)
        ->and($service->getRequiredPorts())->toContain(15672)
        ->and($service->getDependencies())->toBe([])
        ->and($service->getDefaultConfig()->name)->toBe('rabbitmq')
        ->and($service->getDefaultConfig()->type)->toBe('rabbitmq')
        ->and($service->getDefaultConfig()->version)->toBe('3-management')
        ->and($service->getDefaultConfig()->port)->toBe(5672)
        ->and($service->getDefaultConfig()->additionalPorts)->toBe([15672])
        ->and($service->getDefaultConfig()->enabled)->toBeFalse()
        ->and($service->getDefaultConfig()->environmentVariables)->toBe([
            'RABBITMQ_DEFAULT_USER' => 'seaman',
            'RABBITMQ_DEFAULT_PASS' => 'seaman',
        ]);
});

test('rabbitmq service has correct health check', function () {
    $service = new RabbitmqService();
    $healthCheck = $service->getHealthCheck();

    expect($healthCheck)->not->toBeNull();
    assert($healthCheck !== null);

    expect($healthCheck->test)->toBe(['CMD-SHELL', 'rabbitmq-diagnostics -q ping'])
        ->and($healthCheck->interval)->toBe('10s')
        ->and($healthCheck->timeout)->toBe('5s')
        ->and($healthCheck->retries)->toBe(5);
});

test('rabbitmq service generates compose config', function () {
    $service = new RabbitmqService();
    $config = $service->getDefaultConfig();

    $composeConfig = $service->generateComposeConfig($config);

    expect($composeConfig)->toHaveKey('image')
        ->and($composeConfig)->toHaveKey('environment')
        ->and($composeConfig)->toHaveKey('ports')
        ->and($composeConfig)->toHaveKey('volumes')
        ->and($composeConfig)->toHaveKey('healthcheck')
        ->and($composeConfig['image'])->toBe('rabbitmq:3-management')
        ->and($composeConfig['volumes'])->toBe(['rabbitmq_data:/var/lib/rabbitmq']);
});
