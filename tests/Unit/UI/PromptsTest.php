<?php

declare(strict_types=1);

// ABOUTME: Tests for Prompts wrapper in headless mode.
// ABOUTME: Validates that prompts use presets and defaults correctly.

namespace Seaman\Tests\Unit\UI;

use Seaman\Exception\HeadlessModeException;
use Seaman\UI\HeadlessMode;
use Seaman\UI\Prompts;

beforeEach(function (): void {
    HeadlessMode::reset();
    HeadlessMode::enable();
});

afterEach(function (): void {
    HeadlessMode::reset();
});

// confirm() tests

test('confirm returns preset value when available', function (): void {
    HeadlessMode::preset(['Enable feature?' => true]);

    $result = Prompts::confirm('Enable feature?', default: false);

    expect($result)->toBeTrue();
});

test('confirm returns default when no preset', function (): void {
    $result = Prompts::confirm('Enable feature?', default: true);

    expect($result)->toBeTrue();
});

test('confirm returns false default when no preset', function (): void {
    $result = Prompts::confirm('Enable feature?', default: false);

    expect($result)->toBeFalse();
});

// select() tests

test('select returns preset value when available', function (): void {
    HeadlessMode::preset(['Choose option' => 'b']);

    $result = Prompts::select(
        'Choose option',
        options: ['a' => 'Option A', 'b' => 'Option B'],
        default: 'a',
    );

    expect($result)->toBe('b');
});

test('select returns default when no preset', function (): void {
    $result = Prompts::select(
        'Choose option',
        options: ['a' => 'Option A', 'b' => 'Option B'],
        default: 'a',
    );

    expect($result)->toBe('a');
});

test('select throws when no default and no preset', function (): void {
    Prompts::select(
        'Choose option',
        options: ['a' => 'Option A', 'b' => 'Option B'],
        default: null,
    );
})->throws(HeadlessModeException::class, 'Choose option');

// multiselect() tests

test('multiselect returns preset value when available', function (): void {
    HeadlessMode::preset(['Select services' => ['redis', 'mailpit']]);

    $result = Prompts::multiselect(
        'Select services',
        options: ['redis' => 'Redis', 'mailpit' => 'Mailpit', 'minio' => 'Minio'],
        default: [],
    );

    expect($result)->toBe(['redis', 'mailpit']);
});

test('multiselect returns default when no preset', function (): void {
    $result = Prompts::multiselect(
        'Select services',
        options: ['redis' => 'Redis', 'mailpit' => 'Mailpit'],
        default: ['redis'],
    );

    expect($result)->toBe(['redis']);
});

// text() tests

test('text returns preset value when available', function (): void {
    HeadlessMode::preset(['Enter name' => 'my-project']);

    $result = Prompts::text('Enter name', default: 'default-name');

    expect($result)->toBe('my-project');
});

test('text returns default when no preset', function (): void {
    $result = Prompts::text('Enter name', default: 'default-name');

    expect($result)->toBe('default-name');
});

// info() tests

test('info outputs message in headless mode', function (): void {
    // Just verify it doesn't throw - output goes to Terminal
    Prompts::info('Test info message');
    expect(true)->toBeTrue();
});

// table() tests

test('table outputs formatted table in headless mode', function (): void {
    // Just verify it doesn't throw - output goes to Terminal
    Prompts::table(['Name', 'Value'], [['foo', 'bar']]);
    expect(true)->toBeTrue();
});
