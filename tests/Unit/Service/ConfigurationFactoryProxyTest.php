<?php

declare(strict_types=1);

// ABOUTME: Tests for ConfigurationFactory proxy handling.
// ABOUTME: Validates ProxyConfig creation based on useProxy choice.

namespace Seaman\Tests\Unit\Service;

use Seaman\Enum\PhpVersion;
use Seaman\Enum\ProjectType;
use Seaman\Enum\Service;
use Seaman\Service\ConfigurationFactory;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\ValueObject\InitializationChoices;
use Seaman\ValueObject\XdebugConfig;

test('creates Configuration with enabled proxy when useProxy is true', function () {
    $registry = ServiceRegistry::create();
    $factory = new ConfigurationFactory($registry);

    $choices = new InitializationChoices(
        projectName: 'myproject',
        phpVersion: PhpVersion::Php84,
        database: Service::PostgreSQL,
        services: [],
        xdebug: XdebugConfig::default(),
        generateDevContainer: false,
        useProxy: true,
    );

    $config = $factory->createFromChoices($choices, ProjectType::Existing);

    expect($config->proxy()->enabled)->toBeTrue()
        ->and($config->proxy()->domainPrefix)->toBe('myproject');
});

test('creates Configuration with disabled proxy when useProxy is false', function () {
    $registry = ServiceRegistry::create();
    $factory = new ConfigurationFactory($registry);

    $choices = new InitializationChoices(
        projectName: 'myproject',
        phpVersion: PhpVersion::Php84,
        database: Service::PostgreSQL,
        services: [],
        xdebug: XdebugConfig::default(),
        generateDevContainer: false,
        useProxy: false,
    );

    $config = $factory->createFromChoices($choices, ProjectType::Existing);

    expect($config->proxy()->enabled)->toBeFalse();
});
