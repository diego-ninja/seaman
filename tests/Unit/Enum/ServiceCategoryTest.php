<?php

// ABOUTME: Tests for ServiceCategory enum.
// ABOUTME: Verifies all service categories have correct string values.

declare(strict_types=1);

namespace Seaman\Tests\Unit\Enum;

use Seaman\Enum\ServiceCategory;

test('ServiceCategory has all expected cases with correct values', function (): void {
    expect(ServiceCategory::Database->value)->toBe('database');
    expect(ServiceCategory::Cache->value)->toBe('cache');
    expect(ServiceCategory::Queue->value)->toBe('queue');
    expect(ServiceCategory::Search->value)->toBe('search');
    expect(ServiceCategory::Storage->value)->toBe('storage');
    expect(ServiceCategory::Utility->value)->toBe('utility');
    expect(ServiceCategory::DevTools->value)->toBe('dev-tools');
    expect(ServiceCategory::Proxy->value)->toBe('proxy');
    expect(ServiceCategory::Misc->value)->toBe('misc');
});

test('ServiceCategory has exactly 9 cases', function (): void {
    expect(ServiceCategory::cases())->toHaveCount(9);
});
