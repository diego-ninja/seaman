<?php

// ABOUTME: Tests for LifecycleEvent enum.
// ABOUTME: Verifies all lifecycle events have correct string values.

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin;

use Seaman\Plugin\LifecycleEvent;

test('LifecycleEvent has all expected cases with correct values', function (): void {
    expect(LifecycleEvent::BeforeInit->value)->toBe('before:init');
    expect(LifecycleEvent::AfterInit->value)->toBe('after:init');
    expect(LifecycleEvent::BeforeStart->value)->toBe('before:start');
    expect(LifecycleEvent::AfterStart->value)->toBe('after:start');
    expect(LifecycleEvent::BeforeStop->value)->toBe('before:stop');
    expect(LifecycleEvent::AfterStop->value)->toBe('after:stop');
    expect(LifecycleEvent::BeforeRebuild->value)->toBe('before:rebuild');
    expect(LifecycleEvent::AfterRebuild->value)->toBe('after:rebuild');
    expect(LifecycleEvent::BeforeDestroy->value)->toBe('before:destroy');
    expect(LifecycleEvent::AfterDestroy->value)->toBe('after:destroy');
});

test('LifecycleEvent has exactly 10 cases', function (): void {
    expect(LifecycleEvent::cases())->toHaveCount(10);
});
