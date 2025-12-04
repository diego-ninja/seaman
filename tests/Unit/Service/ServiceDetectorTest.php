<?php

declare(strict_types=1);

// ABOUTME: Tests for ServiceDetector service.
// ABOUTME: Validates fuzzy service detection from docker-compose configs.

namespace Seaman\Tests\Unit\Service;

use Seaman\Enum\Service;
use Seaman\Service\ServiceDetector;

beforeEach(function () {
    $this->detector = new ServiceDetector();
});

test('detects postgresql by image', function () {
    $composeService = ['image' => 'postgres:16'];

    $detected = $this->detector->detectService('db', $composeService);

    expect($detected)->not->toBeNull()
        ->and($detected->type)->toBe(Service::PostgreSQL)
        ->and($detected->version)->toBe('16')
        ->and($detected->isHighConfidence())->toBeTrue();
});

test('detects redis by image', function () {
    $composeService = ['image' => 'redis:7-alpine'];

    $detected = $this->detector->detectService('cache', $composeService);

    expect($detected)->not->toBeNull()
        ->and($detected->type)->toBe(Service::Redis)
        ->and($detected->version)->toBe('7-alpine');
});

test('detects mysql by service name', function () {
    $composeService = ['image' => 'some-custom-mysql:latest'];

    $detected = $this->detector->detectService('mysql', $composeService);

    expect($detected)->not->toBeNull()
        ->and($detected->type)->toBe(Service::MySQL);
});

test('detects postgresql by port', function () {
    $composeService = [
        'image' => 'unknown:latest',
        'ports' => ['5432:5432'],
    ];

    $detected = $this->detector->detectService('database', $composeService);

    expect($detected)->not->toBeNull()
        ->and($detected->type)->toBe(Service::PostgreSQL)
        ->and($detected->isMediumConfidence())->toBeTrue();
});

test('detects rabbitmq by image', function () {
    $composeService = ['image' => 'rabbitmq:3.13-management'];

    $detected = $this->detector->detectService('queue', $composeService);

    expect($detected)->not->toBeNull()
        ->and($detected->type)->toBe(Service::RabbitMq)
        ->and($detected->version)->toBe('3.13-management');
});

test('detects mailpit by image', function () {
    $composeService = ['image' => 'axllent/mailpit:latest'];

    $detected = $this->detector->detectService('mail', $composeService);

    expect($detected)->not->toBeNull()
        ->and($detected->type)->toBe(Service::Mailpit);
});

test('returns null for unknown service', function () {
    $composeService = ['image' => 'my-custom-app:latest'];

    $detected = $this->detector->detectService('my-app', $composeService);

    expect($detected)->toBeNull();
});

test('version extraction handles latest', function () {
    $composeService = ['image' => 'postgres:latest'];

    $detected = $this->detector->detectService('db', $composeService);

    expect($detected->version)->toBe('latest');
});

test('version extraction handles no tag', function () {
    $composeService = ['image' => 'postgres'];

    $detected = $this->detector->detectService('db', $composeService);

    expect($detected->version)->toBe('latest');
});

test('detects mongo by image', function () {
    $composeService = ['image' => 'mongo:7'];

    $detected = $this->detector->detectService('db', $composeService);

    expect($detected)->not->toBeNull()
        ->and($detected->type)->toBe(Service::MongoDB);
});

test('detects memcached by image', function () {
    $composeService = ['image' => 'memcached:alpine'];

    $detected = $this->detector->detectService('cache', $composeService);

    expect($detected)->not->toBeNull()
        ->and($detected->type)->toBe(Service::Memcached);
});

test('detects elasticsearch by image', function () {
    $composeService = ['image' => 'elasticsearch:8'];

    $detected = $this->detector->detectService('search', $composeService);

    expect($detected)->not->toBeNull()
        ->and($detected->type)->toBe(Service::Elasticsearch);
});

test('detects minio by image', function () {
    $composeService = ['image' => 'minio/minio:latest'];

    $detected = $this->detector->detectService('storage', $composeService);

    expect($detected)->not->toBeNull()
        ->and($detected->type)->toBe(Service::MinIO);
});

test('detects kafka by image', function () {
    $composeService = ['image' => 'confluentinc/cp-kafka:latest'];

    $detected = $this->detector->detectService('queue', $composeService);

    expect($detected)->not->toBeNull()
        ->and($detected->type)->toBe(Service::Kafka);
});

test('ServiceDetector is readonly', function () {
    $reflection = new \ReflectionClass($this->detector);
    expect($reflection->isReadOnly())->toBeTrue();
});
