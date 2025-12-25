<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Service;

use Seaman\Enum\PhpVersion;
use Seaman\Enum\ProjectType;
use Seaman\Enum\Service;
use Seaman\Service\InitializationSummary;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\XdebugConfig;

test('displays summary with database and services', function () {
    $summary = new InitializationSummary();

    $database = Service::PostgreSQL;
    $services = [Service::Redis, Service::Mailpit];
    $phpConfig = new PhpConfig(
        PhpVersion::Php84,
        new XdebugConfig(true, 'seaman', 'host.docker.internal'),
    );
    $projectType = ProjectType::WebApplication;

    // Should not throw any exceptions
    expect(fn() => $summary->display($database, $services, $phpConfig, $projectType))
        ->not->toThrow(\Exception::class);
});

test('displays summary with no services', function () {
    $summary = new InitializationSummary();

    $database = null;
    $services = [];
    $phpConfig = new PhpConfig(
        PhpVersion::Php83,
        new XdebugConfig(false, 'seaman', 'host.docker.internal'),
    );
    $projectType = ProjectType::Skeleton;

    // Should not throw any exceptions
    expect(fn() => $summary->display($database, $services, $phpConfig, $projectType))
        ->not->toThrow(\Exception::class);
});

test('displays summary with xdebug disabled', function () {
    $summary = new InitializationSummary();

    $database = Service::MySQL;
    $services = [Service::Redis];
    $phpConfig = new PhpConfig(
        PhpVersion::Php84,
        new XdebugConfig(false, 'seaman', 'host.docker.internal'),
    );
    $projectType = ProjectType::ApiPlatform;

    // Should not throw any exceptions
    expect(fn() => $summary->display($database, $services, $phpConfig, $projectType))
        ->not->toThrow(\Exception::class);
});

test('formats service list correctly when empty', function () {
    $summary = new InitializationSummary();

    $formatted = $summary->formatServiceList([]);

    expect($formatted)->toBe('None');
});

test('formats service list correctly with single service', function () {
    $summary = new InitializationSummary();

    $formatted = $summary->formatServiceList([Service::Redis]);

    expect($formatted)->toBe('Redis');
});

test('formats service list correctly with multiple services', function () {
    $summary = new InitializationSummary();

    $formatted = $summary->formatServiceList([Service::Redis, Service::Mailpit, Service::RabbitMq]);

    expect($formatted)->toBe('Redis, Mailpit, Rabbitmq');
});

test('displays summary with proxy enabled', function () {
    $summary = new InitializationSummary();

    $database = Service::PostgreSQL;
    $services = [Service::Redis];
    $phpConfig = new PhpConfig(
        PhpVersion::Php84,
        new XdebugConfig(true, 'seaman', 'host.docker.internal'),
    );
    $projectType = ProjectType::WebApplication;

    expect(fn() => $summary->display($database, $services, $phpConfig, $projectType, false, true))
        ->not->toThrow(\Exception::class);
});

test('displays summary with proxy disabled', function () {
    $summary = new InitializationSummary();

    $database = Service::PostgreSQL;
    $services = [Service::Redis];
    $phpConfig = new PhpConfig(
        PhpVersion::Php84,
        new XdebugConfig(true, 'seaman', 'host.docker.internal'),
    );
    $projectType = ProjectType::WebApplication;

    expect(fn() => $summary->display($database, $services, $phpConfig, $projectType, false, false))
        ->not->toThrow(\Exception::class);
});
