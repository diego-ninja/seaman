<?php

declare(strict_types=1);

// ABOUTME: Tests for ServerConfig value object.
// ABOUTME: Validates server configuration immutability and constraints.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\ValueObject\ServerConfig;

test('creates server config with valid type', function () {
    $config = new ServerConfig(
        type: 'symfony',
        port: 8000,
    );

    expect($config->type)->toBe('symfony')
        ->and($config->port)->toBe(8000);
});

test('rejects invalid server type', function () {
    new ServerConfig(
        type: 'invalid',
        port: 8000,
    );
})->throws(\InvalidArgumentException::class, 'Invalid server type');

test('rejects invalid port', function () {
    new ServerConfig(
        type: 'symfony',
        port: 100000,
    );
})->throws(\InvalidArgumentException::class, 'Invalid port');

test('accepts all valid server types', function (string $type) {
    $config = new ServerConfig(
        type: $type,
        port: 8000,
    );

    expect($config->type)->toBe($type);
})->with(['symfony', 'nginx-fpm', 'frankenphp']);
