<?php

// ABOUTME: Tests for the LifecycleEventData value object.
// ABOUTME: Validates immutability and property access.

declare(strict_types=1);

namespace Tests\Unit\Plugin;

use Seaman\Plugin\LifecycleEventData;

test('creates lifecycle event data with all properties', function (): void {
    $data = new LifecycleEventData(
        event: 'before:start',
        projectRoot: '/path/to/project',
        service: 'nginx',
    );

    expect($data->event)->toBe('before:start')
        ->and($data->projectRoot)->toBe('/path/to/project')
        ->and($data->service)->toBe('nginx');
});

test('creates lifecycle event data without optional service', function (): void {
    $data = new LifecycleEventData(
        event: 'after:init',
        projectRoot: '/another/path',
    );

    expect($data->event)->toBe('after:init')
        ->and($data->projectRoot)->toBe('/another/path')
        ->and($data->service)->toBeNull();
});

test('is readonly', function (): void {
    $data = new LifecycleEventData(
        event: 'before:rebuild',
        projectRoot: '/test',
    );

    expect((new \ReflectionClass($data))->isReadOnly())->toBeTrue();
});
