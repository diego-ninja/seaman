<?php

declare(strict_types=1);

// ABOUTME: Tests for CustomServiceCollection value object.
// ABOUTME: Validates custom services collection behavior.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\ValueObject\CustomServiceCollection;

test('creates empty collection', function () {
    $collection = new CustomServiceCollection();

    expect($collection->all())->toBeEmpty()
        ->and($collection->count())->toBe(0);
});

test('creates collection with services', function () {
    $services = [
        'my-app' => ['image' => 'myapp:latest'],
        'cache' => ['image' => 'redis:7'],
    ];

    $collection = new CustomServiceCollection($services);

    expect($collection->all())->toHaveCount(2)
        ->and($collection->count())->toBe(2);
});

test('has service', function () {
    $services = ['my-app' => ['image' => 'myapp:latest']];
    $collection = new CustomServiceCollection($services);

    expect($collection->has('my-app'))->toBeTrue()
        ->and($collection->has('non-existent'))->toBeFalse();
});

test('get service', function () {
    $serviceConfig = ['image' => 'myapp:latest', 'ports' => ['8080:80']];
    $services = ['my-app' => $serviceConfig];
    $collection = new CustomServiceCollection($services);

    expect($collection->get('my-app'))->toBe($serviceConfig);
});

test('get throws for non existent service', function () {
    $collection = new CustomServiceCollection();

    $collection->get('non-existent');
})->throws(\InvalidArgumentException::class);

test('add service returns new collection', function () {
    $collection = new CustomServiceCollection();
    $newCollection = $collection->add('my-app', ['image' => 'myapp:latest']);

    expect($collection->all())->toBeEmpty()
        ->and($newCollection->all())->toHaveCount(1)
        ->and($newCollection->has('my-app'))->toBeTrue();
});

test('is immutable', function () {
    $collection = new CustomServiceCollection(['app1' => ['image' => 'test']]);
    $collection->add('app2', ['image' => 'test2']);

    expect($collection->all())->toHaveCount(1)
        ->and($collection->has('app2'))->toBeFalse();
});

test('isEmpty returns true for empty collection', function () {
    $collection = new CustomServiceCollection();

    expect($collection->isEmpty())->toBeTrue();
});

test('isEmpty returns false for non-empty collection', function () {
    $collection = new CustomServiceCollection(['app' => ['image' => 'test']]);

    expect($collection->isEmpty())->toBeFalse();
});

test('names returns list of service names', function () {
    $services = [
        'app1' => ['image' => 'test1'],
        'app2' => ['image' => 'test2'],
    ];
    $collection = new CustomServiceCollection($services);

    expect($collection->names())->toBe(['app1', 'app2']);
});

test('CustomServiceCollection is readonly', function () {
    $collection = new CustomServiceCollection();

    $reflection = new \ReflectionClass($collection);
    expect($reflection->isReadOnly())->toBeTrue();
});
