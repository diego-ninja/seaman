<?php

declare(strict_types=1);

// ABOUTME: Unit tests for PortAllocation value object.
// ABOUTME: Tests port mapping and alternative detection.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\ValueObject\PortAllocation;

test('empty allocation returns desired port', function () {
    $allocation = new PortAllocation();

    expect($allocation->getPort('mysql', 3306))->toBe(3306);
    expect($allocation->getPort('redis', 6379))->toBe(6379);
});

test('withPort creates new allocation with mapping', function () {
    $allocation = new PortAllocation();
    $newAllocation = $allocation->withPort('mysql', 3306, 3307);

    // Original is unchanged (immutable)
    expect($allocation->getPort('mysql', 3306))->toBe(3306);

    // New allocation has the mapping
    expect($newAllocation->getPort('mysql', 3306))->toBe(3307);
});

test('hasAlternatives returns false when all ports match', function () {
    $allocation = new PortAllocation();
    $allocation = $allocation->withPort('mysql', 3306, 3306);
    $allocation = $allocation->withPort('redis', 6379, 6379);

    expect($allocation->hasAlternatives())->toBeFalse();
});

test('hasAlternatives returns true when any port differs', function () {
    $allocation = new PortAllocation();
    $allocation = $allocation->withPort('mysql', 3306, 3306);
    $allocation = $allocation->withPort('redis', 6379, 6380); // Differs!

    expect($allocation->hasAlternatives())->toBeTrue();
});

test('all returns complete allocation map', function () {
    $allocation = new PortAllocation();
    $allocation = $allocation->withPort('mysql', 3306, 3307);
    $allocation = $allocation->withPort('mysql', 33060, 33061); // Additional port
    $allocation = $allocation->withPort('redis', 6379, 6379);

    $all = $allocation->all();

    expect($all)->toHaveKey('mysql');
    expect($all)->toHaveKey('redis');
    expect($all['mysql'])->toBe([3306 => 3307, 33060 => 33061]);
    expect($all['redis'])->toBe([6379 => 6379]);
});
