<?php

declare(strict_types=1);

// ABOUTME: Tests for AsSeamanPlugin attribute.
// ABOUTME: Validates plugin metadata storage and defaults.

namespace Seaman\Tests\Unit\Plugin\Attribute;

use Seaman\Plugin\Attribute\AsSeamanPlugin;

test('AsSeamanPlugin stores plugin metadata', function (): void {
    $attribute = new AsSeamanPlugin(
        name: 'test-plugin',
        version: '1.0.0',
        description: 'A test plugin',
        requires: ['seaman/core:^1.0'],
    );

    expect($attribute->name)->toBe('test-plugin');
    expect($attribute->version)->toBe('1.0.0');
    expect($attribute->description)->toBe('A test plugin');
    expect($attribute->requires)->toBe(['seaman/core:^1.0']);
});

test('AsSeamanPlugin has sensible defaults', function (): void {
    $attribute = new AsSeamanPlugin(
        name: 'minimal-plugin',
    );

    expect($attribute->version)->toBe('1.0.0');
    expect($attribute->description)->toBe('');
    expect($attribute->requires)->toBe([]);
});

test('AsSeamanPlugin targets classes only', function (): void {
    $reflection = new \ReflectionClass(AsSeamanPlugin::class);
    $attributes = $reflection->getAttributes(\Attribute::class);

    expect($attributes)->toHaveCount(1);

    $attrInstance = $attributes[0]->newInstance();
    expect($attrInstance->flags)->toBe(\Attribute::TARGET_CLASS);
});
