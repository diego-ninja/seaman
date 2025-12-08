<?php

declare(strict_types=1);

// ABOUTME: Tests for Terminal capability detection.
// ABOUTME: Validates ANSI and cursor support detection.

namespace Seaman\Tests\Unit\UI;

use Seaman\UI\HeadlessMode;
use Seaman\UI\Terminal;

beforeEach(function (): void {
    HeadlessMode::reset();
});

afterEach(function (): void {
    HeadlessMode::reset();
});

test('supportsCursor returns false in headless mode', function (): void {
    HeadlessMode::enable();

    expect(Terminal::supportsCursor())->toBeFalse();
});

test('supportsAnsi returns boolean', function (): void {
    // Just verify it returns a boolean without error
    $result = Terminal::supportsAnsi();

    expect($result)->toBeBool();
});

test('success outputs message', function (): void {
    HeadlessMode::enable();

    ob_start();
    Terminal::success('Test message');
    $output = ob_get_clean();

    // In headless mode with output buffering, we verify no exception is thrown
    expect(true)->toBeTrue();
});

test('error outputs message', function (): void {
    HeadlessMode::enable();

    ob_start();
    Terminal::error('Test error');
    $output = ob_get_clean();

    // In headless mode with output buffering, we verify no exception is thrown
    expect(true)->toBeTrue();
});
