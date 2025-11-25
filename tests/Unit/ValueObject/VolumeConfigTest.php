<?php

declare(strict_types=1);

// ABOUTME: Tests for VolumeConfig value object.
// ABOUTME: Validates volume configuration and persistence settings.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\ValueObject\VolumeConfig;

test('creates volume config with persistent volumes', function () {
    $config = new VolumeConfig(
        persist: ['database', 'redis'],
    );

    expect($config->persist)->toBe(['database', 'redis']);
});

test('creates volume config with empty persist list', function () {
    $config = new VolumeConfig(
        persist: [],
    );

    expect($config->persist)->toBe([]);
});

test('checks if volume should persist', function () {
    $config = new VolumeConfig(
        persist: ['database', 'redis'],
    );

    expect($config->shouldPersist('database'))->toBeTrue()
        ->and($config->shouldPersist('redis'))->toBeTrue()
        ->and($config->shouldPersist('mailpit'))->toBeFalse();
});
