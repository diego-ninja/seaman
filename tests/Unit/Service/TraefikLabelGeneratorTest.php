<?php

declare(strict_types=1);

// ABOUTME: Tests for TraefikLabelGenerator service.
// ABOUTME: Validates Traefik label generation for different service types.

namespace Seaman\Tests\Unit\Service;

use Seaman\Enum\PhpVersion;
use Seaman\Enum\Service;
use Seaman\Service\TraefikLabelGenerator;
use Seaman\ValueObject\ProxyConfig;
use Seaman\ValueObject\ServiceConfig;

test('generates labels for ProxyOnly service (App)', function () {
    $generator = new TraefikLabelGenerator();
    $service = new ServiceConfig(
        name: 'app',
        enabled: true,
        type: Service::App,
        version: PhpVersion::Php84->value,
        port: 80,
        additionalPorts: [],
        environmentVariables: [],
    );
    $proxy = new ProxyConfig(
        enabled: true,
        domainPrefix: 'myproject',
        certResolver: 'mkcert',
        dashboard: true,
    );

    $labels = $generator->generateLabels($service, $proxy);

    expect($labels)->toContain('traefik.enable=true')
        ->and($labels)->toContain('traefik.http.routers.app.rule=Host(`app.myproject.local`)')
        ->and($labels)->toContain('traefik.http.routers.app.entrypoints=websecure')
        ->and($labels)->toContain('traefik.http.routers.app.tls=true')
        ->and($labels)->toContain('traefik.http.services.app.loadbalancer.server.port=80');
});

test('generates labels for ProxyOnly service (Mailpit)', function () {
    $generator = new TraefikLabelGenerator();
    $service = new ServiceConfig(
        name: 'mailpit',
        enabled: true,
        type: Service::Mailpit,
        version: 'latest',
        port: 8025,
        additionalPorts: [1025],
        environmentVariables: [],
    );
    $proxy = new ProxyConfig(
        enabled: true,
        domainPrefix: 'testproject',
        certResolver: 'selfsigned',
        dashboard: true,
    );

    $labels = $generator->generateLabels($service, $proxy);

    expect($labels)->toContain('traefik.enable=true')
        ->and($labels)->toContain('traefik.http.routers.mailpit.rule=Host(`mailpit.testproject.local`)')
        ->and($labels)->toContain('traefik.http.routers.mailpit.entrypoints=websecure')
        ->and($labels)->toContain('traefik.http.routers.mailpit.tls=true')
        ->and($labels)->toContain('traefik.http.services.mailpit.loadbalancer.server.port=8025');
});

test('disables Traefik for DirectPort service (PostgreSQL)', function () {
    $generator = new TraefikLabelGenerator();
    $service = new ServiceConfig(
        name: 'postgres',
        enabled: true,
        type: Service::PostgreSQL,
        version: '16',
        port: 5432,
        additionalPorts: [],
        environmentVariables: [],
    );
    $proxy = new ProxyConfig(
        enabled: true,
        domainPrefix: 'myproject',
        certResolver: 'mkcert',
        dashboard: true,
    );

    $labels = $generator->generateLabels($service, $proxy);

    expect($labels)->toEqual(['traefik.enable=false']);
});

test('disables Traefik for DirectPort service (MySQL)', function () {
    $generator = new TraefikLabelGenerator();
    $service = new ServiceConfig(
        name: 'mysql',
        enabled: true,
        type: Service::MySQL,
        version: '8.0',
        port: 3306,
        additionalPorts: [],
        environmentVariables: [],
    );
    $proxy = new ProxyConfig(
        enabled: true,
        domainPrefix: 'myproject',
        certResolver: 'mkcert',
        dashboard: true,
    );

    $labels = $generator->generateLabels($service, $proxy);

    expect($labels)->toEqual(['traefik.enable=false']);
});

test('disables Traefik for DirectPort service (Redis)', function () {
    $generator = new TraefikLabelGenerator();
    $service = new ServiceConfig(
        name: 'redis',
        enabled: true,
        type: Service::Redis,
        version: '7',
        port: 6379,
        additionalPorts: [],
        environmentVariables: [],
    );
    $proxy = new ProxyConfig(
        enabled: true,
        domainPrefix: 'myproject',
        certResolver: 'mkcert',
        dashboard: true,
    );

    $labels = $generator->generateLabels($service, $proxy);

    expect($labels)->toEqual(['traefik.enable=false']);
});

test('TraefikLabelGenerator is readonly', function () {
    $generator = new TraefikLabelGenerator();

    $reflection = new \ReflectionClass($generator);
    expect($reflection->isReadOnly())->toBeTrue();
});
