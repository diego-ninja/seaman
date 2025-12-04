<?php

declare(strict_types=1);

// ABOUTME: Tests for Configuration root value object.
// ABOUTME: Validates complete configuration structure.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\Enum\PhpVersion;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\VolumeConfig;
use Seaman\ValueObject\XdebugConfig;

test('creates complete configuration', function (): void {
    $xdebug = new XdebugConfig(true, 'PHPSTORM', 'localhost');
    $php = new PhpConfig(PhpVersion::Php84, $xdebug);
    $services = new ServiceCollection([]);
    $volumes = new VolumeConfig([]);

    $config = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: $php,
        services: $services,
        volumes: $volumes,
    );

    expect($config->projectName)->toBe('test-project')
        ->and($config->version)->toBe('1.0')
        ->and($config->php)->toBe($php)
        ->and($config->services)->toBe($services)
        ->and($config->volumes)->toBe($volumes);
});

test('configuration is immutable', function (): void {
    $xdebug = new XdebugConfig(true, 'PHPSTORM', 'localhost');
    $php = new PhpConfig(PhpVersion::Php84, $xdebug);
    $services = new ServiceCollection([]);
    $volumes = new VolumeConfig([]);

    $config = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: $php,
        services: $services,
        volumes: $volumes,
    );

    $reflection = new \ReflectionClass($config);
    expect($reflection->isReadOnly())->toBeTrue();
});
