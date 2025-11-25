<?php

declare(strict_types=1);

// ABOUTME: Tests for ServiceCollection value object.
// ABOUTME: Validates service collection operations.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;

test('creates empty service collection', function () {
    $collection = new ServiceCollection([]);

    expect($collection->all())->toBe([])
        ->and($collection->enabled())->toBe([])
        ->and($collection->count())->toBe(0);
});

test('creates collection with services', function () {
    $services = [
        'postgresql' => new ServiceConfig('postgresql', true, 'postgresql', '16', 5432, [], []),
        'redis' => new ServiceConfig('redis', true, 'redis', '7-alpine', 6379, [], []),
    ];

    $collection = new ServiceCollection($services);

    expect($collection->count())->toBe(2)
        ->and($collection->has('postgresql'))->toBeTrue()
        ->and($collection->has('redis'))->toBeTrue()
        ->and($collection->has('mysql'))->toBeFalse();
});

test('filters enabled services', function () {
    $services = [
        'postgresql' => new ServiceConfig('postgresql', true, 'postgresql', '16', 5432, [], []),
        'redis' => new ServiceConfig('redis', false, 'redis', '7-alpine', 6379, [], []),
    ];

    $collection = new ServiceCollection($services);
    $enabled = $collection->enabled();

    expect($enabled)->toHaveCount(1)
        ->and($enabled['postgresql'])->toBeInstanceOf(ServiceConfig::class);
});

test('gets service by name', function () {
    $service = new ServiceConfig('postgresql', true, 'postgresql', '16', 5432, [], []);
    $collection = new ServiceCollection(['postgresql' => $service]);

    $retrieved = $collection->get('postgresql');

    expect($retrieved)->toBe($service);
});

test('throws when getting non-existent service', function () {
    $collection = new ServiceCollection([]);
    $collection->get('invalid');
})->throws(\InvalidArgumentException::class);

test('adds new service', function () {
    $collection = new ServiceCollection([]);
    $service = new ServiceConfig('postgresql', true, 'postgresql', '16', 5432, [], []);

    $newCollection = $collection->add('postgresql', $service);

    expect($newCollection->has('postgresql'))->toBeTrue()
        ->and($collection->has('postgresql'))->toBeFalse(); // Original unchanged
});

test('removes service', function () {
    $service = new ServiceConfig('postgresql', true, 'postgresql', '16', 5432, [], []);
    $collection = new ServiceCollection(['postgresql' => $service]);

    $newCollection = $collection->remove('postgresql');

    expect($newCollection->has('postgresql'))->toBeFalse()
        ->and($collection->has('postgresql'))->toBeTrue(); // Original unchanged
});
