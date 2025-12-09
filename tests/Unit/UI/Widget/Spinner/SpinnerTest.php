<?php

declare(strict_types=1);

// ABOUTME: Tests for Spinner in headless mode.
// ABOUTME: Validates static output without forking.

namespace Seaman\Tests\Unit\UI\Widget\Spinner;

use Seaman\UI\HeadlessMode;
use Seaman\UI\Widget\Spinner\Spinner;

beforeEach(function (): void {
    HeadlessMode::reset();
    HeadlessMode::enable();
});

afterEach(function (): void {
    HeadlessMode::reset();
});

test('callback executes in headless mode without forking', function (): void {
    $spinner = new Spinner();
    $spinner->setMessage('Test operation');

    $executed = false;
    $result = $spinner->callback(function () use (&$executed): bool {
        $executed = true;
        return true;
    });

    expect($executed)->toBeTrue();
    expect($result)->toBeTrue();
});

test('callback returns false result in headless mode', function (): void {
    $spinner = new Spinner();
    $spinner->setMessage('Failing operation');

    $result = $spinner->callback(fn(): bool => false);

    expect($result)->toBeFalse();
});

test('callback returns callback result in headless mode', function (): void {
    $spinner = new Spinner();
    $spinner->setMessage('String operation');

    $result = $spinner->callback(fn(): string => 'test-value');

    expect($result)->toBe('test-value');
});
