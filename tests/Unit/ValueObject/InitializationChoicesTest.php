<?php

declare(strict_types=1);

// ABOUTME: Tests for InitializationChoices value object.
// ABOUTME: Validates all initialization choice properties including useProxy.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\Enum\PhpVersion;
use Seaman\Enum\ServerType;
use Seaman\Enum\Service;
use Seaman\ValueObject\InitializationChoices;
use Seaman\ValueObject\XdebugConfig;

test('creates InitializationChoices with all properties including useProxy', function () {
    $xdebug = XdebugConfig::default();

    $choices = new InitializationChoices(
        projectName: 'myproject',
        phpVersion: PhpVersion::Php84,
        server: ServerType::SymfonyServer,
        database: Service::PostgreSQL,
        services: [Service::Redis, Service::Mailpit],
        xdebug: $xdebug,
        generateDevContainer: true,
        useProxy: true,
    );

    expect($choices->projectName)->toBe('myproject')
        ->and($choices->phpVersion)->toBe(PhpVersion::Php84)
        ->and($choices->database)->toBe(Service::PostgreSQL)
        ->and($choices->services)->toBe([Service::Redis, Service::Mailpit])
        ->and($choices->xdebug)->toBe($xdebug)
        ->and($choices->generateDevContainer)->toBeTrue()
        ->and($choices->useProxy)->toBeTrue();
});

test('InitializationChoices useProxy defaults to true', function () {
    $xdebug = XdebugConfig::default();

    $choices = new InitializationChoices(
        projectName: 'myproject',
        phpVersion: PhpVersion::Php84,
        server: ServerType::SymfonyServer,
        database: Service::PostgreSQL,
        services: [],
        xdebug: $xdebug,
        generateDevContainer: false,
    );

    expect($choices->useProxy)->toBeTrue();
});

test('InitializationChoices is immutable', function () {
    $xdebug = XdebugConfig::default();

    $choices = new InitializationChoices(
        projectName: 'myproject',
        phpVersion: PhpVersion::Php84,
        server: ServerType::SymfonyServer,
        database: Service::PostgreSQL,
        services: [],
        xdebug: $xdebug,
        generateDevContainer: false,
    );

    $reflection = new \ReflectionClass($choices);
    expect($reflection->isReadOnly())->toBeTrue();
});
