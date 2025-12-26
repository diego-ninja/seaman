<?php

declare(strict_types=1);

// ABOUTME: Tests for ProvidesCommand attribute.
// ABOUTME: Validates command attribute can be instantiated and targets methods.

namespace Seaman\Tests\Unit\Plugin\Attribute;

use Seaman\Plugin\Attribute\ProvidesCommand;

test('ProvidesCommand can be instantiated', function (): void {
    $attribute = new ProvidesCommand();

    expect($attribute)->toBeInstanceOf(ProvidesCommand::class);
});

test('ProvidesCommand targets methods only', function (): void {
    $reflection = new \ReflectionClass(ProvidesCommand::class);
    $attributes = $reflection->getAttributes(\Attribute::class);

    $attrInstance = $attributes[0]->newInstance();
    expect($attrInstance->flags)->toBe(\Attribute::TARGET_METHOD);
});
