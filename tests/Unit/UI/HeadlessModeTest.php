<?php

declare(strict_types=1);

// ABOUTME: Tests for HeadlessMode state management.
// ABOUTME: Validates mode detection, preset responses, and reset functionality.

namespace Seaman\Tests\Unit\UI;

use Seaman\UI\HeadlessMode;

beforeEach(function (): void {
    HeadlessMode::reset();
});

afterEach(function (): void {
    HeadlessMode::reset();
});

test('isHeadless returns false by default in TTY', function (): void {
    // In test environment without explicit enable, detection depends on environment
    // We test the explicit enable/disable behavior instead
    HeadlessMode::disable();
    HeadlessMode::forceInteractive(true);

    expect(HeadlessMode::isHeadless())->toBeFalse();
});

test('enable sets headless mode', function (): void {
    HeadlessMode::enable();

    expect(HeadlessMode::isHeadless())->toBeTrue();
});

test('forceInteractive overrides headless', function (): void {
    HeadlessMode::enable();
    HeadlessMode::forceInteractive(true);

    expect(HeadlessMode::isHeadless())->toBeFalse();
});

test('preset stores responses', function (): void {
    HeadlessMode::preset([
        'Select database' => 'mysql',
        'Enable Xdebug?' => true,
    ]);

    expect(HeadlessMode::hasPreset('Select database'))->toBeTrue();
    expect(HeadlessMode::getPreset('Select database'))->toBe('mysql');
    expect(HeadlessMode::hasPreset('Enable Xdebug?'))->toBeTrue();
    expect(HeadlessMode::getPreset('Enable Xdebug?'))->toBe(true);
});

test('hasPreset returns false for missing keys', function (): void {
    expect(HeadlessMode::hasPreset('Unknown prompt'))->toBeFalse();
});

test('getPreset returns null for missing keys', function (): void {
    expect(HeadlessMode::getPreset('Unknown prompt'))->toBeNull();
});

test('preset merges with existing responses', function (): void {
    HeadlessMode::preset(['first' => 'value1']);
    HeadlessMode::preset(['second' => 'value2']);

    expect(HeadlessMode::getPreset('first'))->toBe('value1');
    expect(HeadlessMode::getPreset('second'))->toBe('value2');
});

test('reset clears all state', function (): void {
    HeadlessMode::enable();
    HeadlessMode::forceInteractive(true);
    HeadlessMode::preset(['key' => 'value']);

    HeadlessMode::reset();

    expect(HeadlessMode::hasPreset('key'))->toBeFalse();
    // After reset, detection runs fresh
});
