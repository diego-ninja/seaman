<?php

declare(strict_types=1);

// ABOUTME: Tests for HeadlessModeException.
// ABOUTME: Validates exception creation with proper messages.

namespace Seaman\Tests\Unit\Exception;

use Seaman\Exception\HeadlessModeException;

test('missingDefault creates exception with label', function (): void {
    $exception = HeadlessModeException::missingDefault('Select database');

    expect($exception)->toBeInstanceOf(HeadlessModeException::class);
    expect($exception->getMessage())->toContain('Select database');
    expect($exception->getMessage())->toContain('No default');
});
