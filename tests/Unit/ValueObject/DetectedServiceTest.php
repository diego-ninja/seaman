<?php

declare(strict_types=1);

// ABOUTME: Tests for DetectedService value object.
// ABOUTME: Validates detected service data structure.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\Enum\Service;
use Seaman\ValueObject\DetectedService;

test('creates detected service with high confidence', function () {
    $detected = new DetectedService(
        type: Service::PostgreSQL,
        version: '16',
        confidence: 'high',
    );

    expect($detected->type)->toBe(Service::PostgreSQL)
        ->and($detected->version)->toBe('16')
        ->and($detected->confidence)->toBe('high');
});

test('creates detected service with medium confidence', function () {
    $detected = new DetectedService(
        type: Service::Redis,
        version: '7-alpine',
        confidence: 'medium',
    );

    expect($detected->confidence)->toBe('medium');
});

test('is high confidence helper', function () {
    $high = new DetectedService(Service::MySQL, '8.0', 'high');
    $medium = new DetectedService(Service::Redis, '7', 'medium');

    expect($high->isHighConfidence())->toBeTrue()
        ->and($medium->isHighConfidence())->toBeFalse();
});

test('version defaults to latest', function () {
    $detected = new DetectedService(Service::PostgreSQL);

    expect($detected->version)->toBe('latest');
});

test('confidence defaults to high', function () {
    $detected = new DetectedService(Service::PostgreSQL, '16');

    expect($detected->confidence)->toBe('high');
});

test('DetectedService is readonly', function () {
    $detected = new DetectedService(Service::PostgreSQL);

    $reflection = new \ReflectionClass($detected);
    expect($reflection->isReadOnly())->toBeTrue();
});
