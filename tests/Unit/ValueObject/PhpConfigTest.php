<?php

declare(strict_types=1);

// ABOUTME: Tests for PhpConfig and XdebugConfig value objects.
// ABOUTME: Validates PHP configuration including server type.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\Enum\PhpVersion;
use Seaman\Enum\ServerType;
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

test('creates php config', function () {
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $config = new PhpConfig(
        version: PhpVersion::Php84,
        xdebug: $xdebug,
    );

    expect($config->version)->toBe(PhpVersion::Php84)
        ->and($config->xdebug)->toBe($xdebug);
});

test('rejects invalid php version', function () {
    // PhpVersion::Unsupported is not in the supported list
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    new PhpConfig(
        version: PhpVersion::Unsupported,
        xdebug: $xdebug,
    );
})->throws(\InvalidArgumentException::class, 'Unsupported PHP version');

test('accepts valid php versions', function (PhpVersion $version) {
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $config = new PhpConfig(
        version: $version,
        xdebug: $xdebug,
    );

    expect($config->version)->toBe($version);
})->with([PhpVersion::Php83, PhpVersion::Php84, PhpVersion::Php85]);

test('PhpConfig includes server type', function () {
    $config = new PhpConfig(
        version: PhpVersion::Php84,
        server: ServerType::FrankenPhpClassic,
        xdebug: new XdebugConfig(enabled: false, ideKey: 'PHPSTORM', clientHost: 'host.docker.internal'),
    );

    expect($config->server)->toBe(ServerType::FrankenPhpClassic);
});

test('PhpConfig defaults server to SymfonyServer', function () {
    $config = new PhpConfig(
        version: PhpVersion::Php84,
        xdebug: new XdebugConfig(enabled: false, ideKey: 'PHPSTORM', clientHost: 'host.docker.internal'),
    );

    expect($config->server)->toBe(ServerType::SymfonyServer);
});
