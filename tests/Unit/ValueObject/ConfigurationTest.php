<?php

declare(strict_types=1);

// ABOUTME: Tests for Configuration root value object.
// ABOUTME: Validates complete configuration structure.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\ServerConfig;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\XdebugConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\VolumeConfig;

test('creates complete configuration', function () {
    $server = new ServerConfig('symfony', 8000);
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig('8.4', ['pdo_pgsql', 'redis'], $xdebug);
    $services = new ServiceCollection([
        'postgresql' => new ServiceConfig('postgresql', true, 'postgresql', '16', 5432, [], []),
    ]);
    $volumes = new VolumeConfig(['database']);

    $config = new Configuration(
        version: '1.0',
        server: $server,
        php: $php,
        services: $services,
        volumes: $volumes,
    );

    expect($config->version)->toBe('1.0')
        ->and($config->server)->toBe($server)
        ->and($config->php)->toBe($php)
        ->and($config->services)->toBe($services)
        ->and($config->volumes)->toBe($volumes);
});

test('configuration is immutable', function () {
    $server = new ServerConfig('symfony', 8000);
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig('8.4', [], $xdebug);
    $services = new ServiceCollection([]);
    $volumes = new VolumeConfig([]);

    $config = new Configuration('1.0', $server, $php, $services, $volumes);

    expect($config)->toBeInstanceOf(Configuration::class);

    // Verify readonly behavior
    $reflection = new \ReflectionClass($config);
    expect($reflection->isReadOnly())->toBeTrue();
});
