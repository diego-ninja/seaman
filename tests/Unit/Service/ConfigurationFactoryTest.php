<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Service;

use Seaman\Enum\PhpVersion;
use Seaman\Enum\ProjectType;
use Seaman\Enum\Service;
use Seaman\Service\ConfigurationFactory;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\ValueObject\InitializationChoices;
use Seaman\ValueObject\XdebugConfig;

test('creates configuration from choices with database and services', function () {
    $registry = ServiceRegistry::create();
    $factory = new ConfigurationFactory($registry);

    $choices = new InitializationChoices(
        projectName: 'test-project',
        phpVersion: PhpVersion::Php84,
        database: Service::PostgreSQL,
        services: [Service::Redis, Service::Mailpit],
        xdebug: new XdebugConfig(true, 'seaman', 'host.docker.internal'),
        generateDevContainer: false,
    );

    $config = $factory->createFromChoices($choices, ProjectType::WebApplication);

    expect($config->php->version)->toBe(PhpVersion::Php84)
        ->and($config->php->xdebug->enabled)->toBeTrue()
        ->and($config->projectType)->toBe(ProjectType::WebApplication)
        ->and($config->services->has('postgresql'))->toBeTrue()
        ->and($config->services->has('redis'))->toBeTrue()
        ->and($config->services->has('mailpit'))->toBeTrue()
        ->and($config->services->count())->toBe(3);
});

test('creates configuration without database when None selected', function () {
    $registry = ServiceRegistry::create();
    $factory = new ConfigurationFactory($registry);

    $choices = new InitializationChoices(
        projectName: 'test-project',
        phpVersion: PhpVersion::Php83,
        database: Service::None,
        services: [Service::Redis],
        xdebug: new XdebugConfig(false, 'seaman', 'host.docker.internal'),
        generateDevContainer: true,
    );

    $config = $factory->createFromChoices($choices, ProjectType::Skeleton);

    expect($config->services->has('postgresql'))->toBeFalse()
        ->and($config->services->has('redis'))->toBeTrue()
        ->and($config->services->count())->toBe(1);
});

test('includes database in persist volumes', function () {
    $registry = ServiceRegistry::create();
    $factory = new ConfigurationFactory($registry);

    $choices = new InitializationChoices(
        projectName: 'test-project',
        phpVersion: PhpVersion::Php84,
        database: Service::PostgreSQL,
        services: [],
        xdebug: new XdebugConfig(false, 'seaman', 'host.docker.internal'),
        generateDevContainer: false,
    );

    $config = $factory->createFromChoices($choices, ProjectType::Existing);

    expect($config->volumes->persist)->toContain('postgresql');
});

test('includes persistable services in persist volumes', function () {
    $registry = ServiceRegistry::create();
    $factory = new ConfigurationFactory($registry);

    $choices = new InitializationChoices(
        projectName: 'test-project',
        phpVersion: PhpVersion::Php84,
        database: Service::None,
        services: [Service::Redis, Service::MongoDB, Service::Elasticsearch],
        xdebug: new XdebugConfig(false, 'seaman', 'host.docker.internal'),
        generateDevContainer: false,
    );

    $config = $factory->createFromChoices($choices, ProjectType::Microservice);

    expect($config->volumes->persist)->toContain('redis')
        ->and($config->volumes->persist)->toContain('mongodb')
        ->and($config->volumes->persist)->toContain('elasticsearch');
});

test('does not include non-persistable services in persist volumes', function () {
    $registry = ServiceRegistry::create();
    $factory = new ConfigurationFactory($registry);

    $choices = new InitializationChoices(
        projectName: 'test-project',
        phpVersion: PhpVersion::Php84,
        database: Service::None,
        services: [Service::Mailpit, Service::Dozzle],
        xdebug: new XdebugConfig(false, 'seaman', 'host.docker.internal'),
        generateDevContainer: false,
    );

    $config = $factory->createFromChoices($choices, ProjectType::WebApplication);

    expect($config->volumes->persist)->not->toContain('mailpit')
        ->and($config->volumes->persist)->not->toContain('dozzle');
});
