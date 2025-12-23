<?php

declare(strict_types=1);

// ABOUTME: Tests for OnLifecycle attribute.
// ABOUTME: Validates lifecycle event storage and defaults.

namespace Seaman\Tests\Unit\Plugin\Attribute;

use Seaman\Plugin\Attribute\OnLifecycle;

test('OnLifecycle stores event and priority', function (): void {
    $attribute = new OnLifecycle(
        event: 'before:start',
        priority: 10,
    );

    expect($attribute->event)->toBe('before:start');
    expect($attribute->priority)->toBe(10);
});

test('OnLifecycle defaults to priority 0', function (): void {
    $attribute = new OnLifecycle(event: 'after:init');

    expect($attribute->priority)->toBe(0);
});

test('OnLifecycle targets methods only', function (): void {
    $reflection = new \ReflectionClass(OnLifecycle::class);
    $attributes = $reflection->getAttributes(\Attribute::class);

    $attrInstance = $attributes[0]->newInstance();
    expect($attrInstance->flags)->toBe(\Attribute::TARGET_METHOD);
});
