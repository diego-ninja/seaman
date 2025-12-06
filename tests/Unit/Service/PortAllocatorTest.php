<?php

declare(strict_types=1);

// ABOUTME: Unit tests for PortAllocator service.
// ABOUTME: Tests port allocation logic using real PortChecker.

namespace Seaman\Tests\Unit\Service;

use Seaman\Enum\PhpVersion;
use Seaman\Enum\Service;
use Seaman\Service\PortAllocator;
use Seaman\Service\PortChecker;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\VolumeConfig;
use Seaman\ValueObject\XdebugConfig;

test('allocate with empty service collection returns empty allocation', function () {
    $portChecker = new PortChecker();
    $allocator = new PortAllocator($portChecker);

    $config = new Configuration(
        projectName: 'test',
        version: '1.0',
        php: new PhpConfig(PhpVersion::Php84, new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal')),
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
    );

    $allocation = $allocator->allocate($config);

    expect($allocation->all())->toBe([]);
    expect($allocation->hasAlternatives())->toBeFalse();
});

test('allocate skips disabled services', function () {
    $portChecker = new PortChecker();
    $allocator = new PortAllocator($portChecker);

    $serviceConfig = new ServiceConfig(
        name: 'mysql',
        enabled: false, // Disabled!
        type: Service::MySQL,
        version: '8.0',
        port: 3306,
        additionalPorts: [],
        environmentVariables: [],
    );

    $config = new Configuration(
        projectName: 'test',
        version: '1.0',
        php: new PhpConfig(PhpVersion::Php84, new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal')),
        services: new ServiceCollection(['mysql' => $serviceConfig]),
        volumes: new VolumeConfig([]),
    );

    $allocation = $allocator->allocate($config);

    // No allocation for disabled service
    expect($allocation->all())->toBe([]);
});

test('allocate skips zero ports (like sqlite)', function () {
    $portChecker = new PortChecker();
    $allocator = new PortAllocator($portChecker);

    $serviceConfig = new ServiceConfig(
        name: 'sqlite',
        enabled: true,
        type: Service::SQLite,
        version: 'latest',
        port: 0, // SQLite has no port
        additionalPorts: [],
        environmentVariables: [],
    );

    $config = new Configuration(
        projectName: 'test',
        version: '1.0',
        php: new PhpConfig(PhpVersion::Php84, new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal')),
        services: new ServiceCollection(['sqlite' => $serviceConfig]),
        volumes: new VolumeConfig([]),
    );

    $allocation = $allocator->allocate($config);

    // No allocation for zero port
    expect($allocation->all())->toBe([]);
});
