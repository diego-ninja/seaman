<?php

declare(strict_types=1);

// ABOUTME: Tests for cache and storage service implementations.
// ABOUTME: Validates Redis, Mailpit, and MinIO services.

namespace Seaman\Tests\Unit\Service\Container;

use Seaman\Service\Container\RedisService;
use Seaman\Service\Container\MailpitService;
use Seaman\Service\Container\MinioService;

test('redis service has correct configuration', function () {
    $service = new RedisService();

    expect($service->getName())->toBe('redis')
        ->and($service->getDisplayName())->toBe('Redis')
        ->and($service->getDescription())->toBe('Redis cache and session storage')
        ->and($service->getRequiredPorts())->toBe([6379])
        ->and($service->getDependencies())->toBe([])
        ->and($service->getDefaultConfig()->name)->toBe('redis')
        ->and($service->getDefaultConfig()->type)->toBe('redis')
        ->and($service->getDefaultConfig()->version)->toBe('7-alpine')
        ->and($service->getDefaultConfig()->port)->toBe(6379)
        ->and($service->getDefaultConfig()->enabled)->toBeTrue()
        ->and($service->getDefaultConfig()->additionalPorts)->toBe([]);
});

test('redis service has correct health check', function () {
    $service = new RedisService();
    $healthCheck = $service->getHealthCheck();

    expect($healthCheck)->not->toBeNull();
    assert($healthCheck !== null);

    expect($healthCheck->test)->toBe(['CMD', 'redis-cli', 'ping'])
        ->and($healthCheck->interval)->toBe('10s')
        ->and($healthCheck->timeout)->toBe('5s')
        ->and($healthCheck->retries)->toBe(5);
});

test('redis service generates compose config', function () {
    $service = new RedisService();
    $config = $service->getDefaultConfig();

    $composeConfig = $service->generateComposeConfig($config);

    expect($composeConfig)->toHaveKey('image')
        ->and($composeConfig)->toHaveKey('ports')
        ->and($composeConfig)->toHaveKey('networks')
        ->and($composeConfig)->toHaveKey('healthcheck')
        ->and($composeConfig['image'])->toBe('redis:7-alpine');
});

test('mailpit service has correct configuration', function () {
    $service = new MailpitService();

    expect($service->getName())->toBe('mailpit')
        ->and($service->getDisplayName())->toBe('Mailpit')
        ->and($service->getDescription())->toBe('Email testing tool - captures and displays emails')
        ->and($service->getRequiredPorts())->toContain(8025)
        ->and($service->getRequiredPorts())->toContain(1025)
        ->and($service->getDependencies())->toBe([])
        ->and($service->getDefaultConfig()->name)->toBe('mailpit')
        ->and($service->getDefaultConfig()->type)->toBe('mailpit')
        ->and($service->getDefaultConfig()->version)->toBe('latest')
        ->and($service->getDefaultConfig()->port)->toBe(8025)
        ->and($service->getDefaultConfig()->additionalPorts)->toBe([1025])
        ->and($service->getDefaultConfig()->enabled)->toBeFalse();
});

test('mailpit service has correct health check', function () {
    $service = new MailpitService();
    $healthCheck = $service->getHealthCheck();

    expect($healthCheck->test)->toBe(['CMD', 'wget', '--quiet', '--tries=1', '--spider', 'http://localhost:8025/'])
        ->and($healthCheck->interval)->toBe('10s')
        ->and($healthCheck->timeout)->toBe('5s')
        ->and($healthCheck->retries)->toBe(3);
});

test('mailpit service generates compose config', function () {
    $service = new MailpitService();
    $config = $service->getDefaultConfig();

    $composeConfig = $service->generateComposeConfig($config);

    expect($composeConfig)->toHaveKey('image')
        ->and($composeConfig)->toHaveKey('ports')
        ->and($composeConfig)->toHaveKey('networks')
        ->and($composeConfig)->toHaveKey('healthcheck')
        ->and($composeConfig['image'])->toBe('axllent/mailpit:latest');
});

test('minio service has correct configuration', function () {
    $service = new MinioService();

    expect($service->getName())->toBe('minio')
        ->and($service->getDisplayName())->toBe('MinIO')
        ->and($service->getDescription())->toBe('S3-compatible object storage')
        ->and($service->getRequiredPorts())->toContain(9000)
        ->and($service->getRequiredPorts())->toContain(9001)
        ->and($service->getDependencies())->toBe([])
        ->and($service->getDefaultConfig()->name)->toBe('minio')
        ->and($service->getDefaultConfig()->type)->toBe('minio')
        ->and($service->getDefaultConfig()->version)->toBe('latest')
        ->and($service->getDefaultConfig()->port)->toBe(9000)
        ->and($service->getDefaultConfig()->additionalPorts)->toBe([9001])
        ->and($service->getDefaultConfig()->enabled)->toBeFalse();
});

test('minio service has correct health check', function () {
    $service = new MinioService();
    $healthCheck = $service->getHealthCheck();

    expect($healthCheck->test)->toBe(['CMD', 'curl', '-f', 'http://localhost:9000/minio/health/live'])
        ->and($healthCheck->interval)->toBe('30s')
        ->and($healthCheck->timeout)->toBe('20s')
        ->and($healthCheck->retries)->toBe(3);
});

test('minio service generates compose config', function () {
    $service = new MinioService();
    $config = $service->getDefaultConfig();

    $composeConfig = $service->generateComposeConfig($config);

    expect($composeConfig)->toHaveKey('image')
        ->and($composeConfig)->toHaveKey('command')
        ->and($composeConfig)->toHaveKey('environment')
        ->and($composeConfig)->toHaveKey('ports')
        ->and($composeConfig)->toHaveKey('networks')
        ->and($composeConfig)->toHaveKey('healthcheck')
        ->and($composeConfig['image'])->toBe('minio/minio:latest');
});
