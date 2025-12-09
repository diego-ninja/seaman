<?php

declare(strict_types=1);

// ABOUTME: Unit tests for PortAllocationException.
// ABOUTME: Tests factory methods for exception creation.

namespace Seaman\Tests\Unit\Exception;

use Seaman\Exception\PortAllocationException;

test('noPortsAvailable creates exception with correct message', function () {
    $exception = PortAllocationException::noPortsAvailable('mysql', 3306);

    expect($exception->getMessage())
        ->toContain('mysql')
        ->toContain('3306')
        ->toContain('3316'); // startPort + 10
});

test('userRejected creates exception with correct message', function () {
    $exception = PortAllocationException::userRejected('redis', 6379);

    expect($exception->getMessage())
        ->toContain('redis')
        ->toContain('6379')
        ->toContain('rejected');
});
