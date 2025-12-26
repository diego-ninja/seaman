<?php

declare(strict_types=1);

// ABOUTME: Tests for ProvidesService attribute.
// ABOUTME: Validates service metadata storage and defaults.

namespace Seaman\Tests\Unit\Plugin\Attribute;

use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Enum\ServiceCategory;

test('ProvidesService stores service metadata', function (): void {
    $attribute = new ProvidesService(
        name: 'redis-cluster',
        category: ServiceCategory::Cache,
    );

    expect($attribute->name)->toBe('redis-cluster');
    expect($attribute->category)->toBe(ServiceCategory::Cache);
});

test('ProvidesService defaults to Misc category', function (): void {
    $attribute = new ProvidesService(name: 'custom-service');

    expect($attribute->category)->toBe(ServiceCategory::Misc);
});

test('ProvidesService targets methods only', function (): void {
    $reflection = new \ReflectionClass(ProvidesService::class);
    $attributes = $reflection->getAttributes(\Attribute::class);

    $attrInstance = $attributes[0]->newInstance();
    expect($attrInstance->flags)->toBe(\Attribute::TARGET_METHOD);
});
