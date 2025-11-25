<?php

declare(strict_types=1);

// ABOUTME: Tests for utility value objects.
// ABOUTME: Validates ProcessResult, HealthCheck, and LogOptions.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\ValueObject\ProcessResult;
use Seaman\ValueObject\HealthCheck;
use Seaman\ValueObject\LogOptions;

test('creates process result', function () {
    $result = new ProcessResult(
        exitCode: 0,
        output: 'success',
        errorOutput: '',
    );

    expect($result->exitCode)->toBe(0)
        ->and($result->output)->toBe('success')
        ->and($result->errorOutput)->toBe('')
        ->and($result->isSuccessful())->toBeTrue();
});

test('process result detects failure', function () {
    $result = new ProcessResult(
        exitCode: 1,
        output: '',
        errorOutput: 'error',
    );

    expect($result->isSuccessful())->toBeFalse();
});

test('creates health check', function () {
    $healthCheck = new HealthCheck(
        test: ['CMD', 'pg_isready'],
        interval: '10s',
        timeout: '5s',
        retries: 3,
    );

    expect($healthCheck->test)->toBe(['CMD', 'pg_isready'])
        ->and($healthCheck->interval)->toBe('10s')
        ->and($healthCheck->timeout)->toBe('5s')
        ->and($healthCheck->retries)->toBe(3);
});

test('creates log options with defaults', function () {
    $options = new LogOptions(
        follow: false,
        tail: null,
        since: null,
    );

    expect($options->follow)->toBeFalse()
        ->and($options->tail)->toBeNull()
        ->and($options->since)->toBeNull();
});

test('creates log options with custom values', function () {
    $options = new LogOptions(
        follow: true,
        tail: 100,
        since: '2024-01-01',
    );

    expect($options->follow)->toBeTrue()
        ->and($options->tail)->toBe(100)
        ->and($options->since)->toBe('2024-01-01');
});
