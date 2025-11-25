<?php

declare(strict_types=1);

// ABOUTME: Tests for PhpConfig and XdebugConfig value objects.
// ABOUTME: Validates PHP configuration and Xdebug settings.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\XdebugConfig;

test('creates xdebug config', function () {
    $config = new XdebugConfig(
        enabled: false,
        ideKey: 'PHPSTORM',
        clientHost: 'host.docker.internal',
    );

    expect($config->enabled)->toBeFalse()
        ->and($config->ideKey)->toBe('PHPSTORM')
        ->and($config->clientHost)->toBe('host.docker.internal');
});

test('creates php config with extensions', function () {
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $config = new PhpConfig(
        version: '8.4',
        extensions: ['pdo_pgsql', 'redis', 'intl'],
        xdebug: $xdebug,
    );

    expect($config->version)->toBe('8.4')
        ->and($config->extensions)->toBe(['pdo_pgsql', 'redis', 'intl'])
        ->and($config->xdebug)->toBe($xdebug);
});

test('rejects invalid php version', function () {
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    new PhpConfig(
        version: '7.4',
        extensions: [],
        xdebug: $xdebug,
    );
})->throws(\InvalidArgumentException::class, 'Unsupported PHP version');

test('accepts valid php versions', function (string $version) {
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $config = new PhpConfig(
        version: $version,
        extensions: [],
        xdebug: $xdebug,
    );

    expect($config->version)->toBe($version);
})->with(['8.2', '8.3', '8.4']);
