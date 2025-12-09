<?php

declare(strict_types=1);

// ABOUTME: Tests for ServiceDetector service.
// ABOUTME: Validates fuzzy service detection from docker-compose configs.

namespace Seaman\Tests\Unit\Service;

use Seaman\Enum\Service;
use Seaman\Service\Detector\ServiceDetector;

test('detects postgresql by image', function () {
    $detector = new ServiceDetector();
    $composeService = ['image' => 'postgres:16'];

    $detected = $detector->detectService('db', $composeService);

    expect($detected)->not->toBeNull();
    assert($detected !== null);
    expect($detected->type)->toBe(Service::PostgreSQL)
        ->and($detected->version)->toBe('16')
        ->and($detected->isHighConfidence())->toBeTrue();
});

test('detects redis by image', function () {
    $detector = new ServiceDetector();
    $composeService = ['image' => 'redis:7-alpine'];

    $detected = $detector->detectService('cache', $composeService);

    expect($detected)->not->toBeNull();
    assert($detected !== null);
    expect($detected->type)->toBe(Service::Redis)
        ->and($detected->version)->toBe('7-alpine');
});

test('detects mysql by service name', function () {
    $detector = new ServiceDetector();
    $composeService = ['image' => 'some-custom-mysql:latest'];

    $detected = $detector->detectService('mysql', $composeService);

    expect($detected)->not->toBeNull();
    assert($detected !== null);
    expect($detected->type)->toBe(Service::MySQL);
});

test('detects postgresql by port', function () {
    $detector = new ServiceDetector();
    $composeService = [
        'image' => 'unknown:latest',
        'ports' => ['5432:5432'],
    ];

    $detected = $detector->detectService('database', $composeService);

    expect($detected)->not->toBeNull();
    assert($detected !== null);
    expect($detected->type)->toBe(Service::PostgreSQL)
        ->and($detected->isMediumConfidence())->toBeTrue();
});

test('detects rabbitmq by image', function () {
    $detector = new ServiceDetector();
    $composeService = ['image' => 'rabbitmq:3.13-management'];

    $detected = $detector->detectService('queue', $composeService);

    expect($detected)->not->toBeNull();
    assert($detected !== null);
    expect($detected->type)->toBe(Service::RabbitMq)
        ->and($detected->version)->toBe('3.13-management');
});

test('detects mailpit by image', function () {
    $detector = new ServiceDetector();
    $composeService = ['image' => 'axllent/mailpit:latest'];

    $detected = $detector->detectService('mail', $composeService);

    expect($detected)->not->toBeNull();
    assert($detected !== null);
    expect($detected->type)->toBe(Service::Mailpit);
});

test('returns null for unknown service', function () {
    $detector = new ServiceDetector();
    $composeService = ['image' => 'my-custom-app:latest'];

    $detected = $detector->detectService('my-app', $composeService);

    expect($detected)->toBeNull();
});

test('version extraction handles latest', function () {
    $detector = new ServiceDetector();
    $composeService = ['image' => 'postgres:latest'];

    $detected = $detector->detectService('db', $composeService);

    expect($detected)->not->toBeNull();
    assert($detected !== null);
    expect($detected->version)->toBe('latest');
});

test('version extraction handles no tag', function () {
    $detector = new ServiceDetector();
    $composeService = ['image' => 'postgres'];

    $detected = $detector->detectService('db', $composeService);

    expect($detected)->not->toBeNull();
    assert($detected !== null);
    expect($detected->version)->toBe('latest');
});

test('detects mongo by image', function () {
    $detector = new ServiceDetector();
    $composeService = ['image' => 'mongo:7'];

    $detected = $detector->detectService('db', $composeService);

    expect($detected)->not->toBeNull();
    assert($detected !== null);
    expect($detected->type)->toBe(Service::MongoDB);
});

test('detects memcached by image', function () {
    $detector = new ServiceDetector();
    $composeService = ['image' => 'memcached:alpine'];

    $detected = $detector->detectService('cache', $composeService);

    expect($detected)->not->toBeNull();
    assert($detected !== null);
    expect($detected->type)->toBe(Service::Memcached);
});

test('detects elasticsearch by image', function () {
    $detector = new ServiceDetector();
    $composeService = ['image' => 'elasticsearch:8'];

    $detected = $detector->detectService('search', $composeService);

    expect($detected)->not->toBeNull();
    assert($detected !== null);
    expect($detected->type)->toBe(Service::Elasticsearch);
});

test('detects minio by image', function () {
    $detector = new ServiceDetector();
    $composeService = ['image' => 'minio/minio:latest'];

    $detected = $detector->detectService('storage', $composeService);

    expect($detected)->not->toBeNull();
    assert($detected !== null);
    expect($detected->type)->toBe(Service::MinIO);
});

test('detects kafka by image', function () {
    $detector = new ServiceDetector();
    $composeService = ['image' => 'confluentinc/cp-kafka:latest'];

    $detected = $detector->detectService('queue', $composeService);

    expect($detected)->not->toBeNull();
    assert($detected !== null);
    expect($detected->type)->toBe(Service::Kafka);
});

test('ServiceDetector is readonly', function () {
    $detector = new ServiceDetector();
    $reflection = new \ReflectionClass($detector);
    expect($reflection->isReadOnly())->toBeTrue();
});
