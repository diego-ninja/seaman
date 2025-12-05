<?php

declare(strict_types=1);

// ABOUTME: Tests for ProxyConfig value object.
// ABOUTME: Validates proxy configuration and domain generation.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\ValueObject\ProxyConfig;

test('creates ProxyConfig with all properties', function () {
    $config = new ProxyConfig(
        enabled: true,
        domainPrefix: 'myproject',
        certResolver: 'mkcert',
        dashboard: true,
    );

    expect($config->enabled)->toBeTrue()
        ->and($config->domainPrefix)->toBe('myproject')
        ->and($config->certResolver)->toBe('mkcert')
        ->and($config->dashboard)->toBeTrue();
});

test('creates default ProxyConfig for project', function () {
    $config = ProxyConfig::default('testproject');

    expect($config->enabled)->toBeTrue()
        ->and($config->domainPrefix)->toBe('testproject')
        ->and($config->certResolver)->toBe('selfsigned')
        ->and($config->dashboard)->toBeTrue();
});

test('generates correct domain with default subdomain', function () {
    $config = new ProxyConfig(
        enabled: true,
        domainPrefix: 'myproject',
        certResolver: 'selfsigned',
        dashboard: true,
    );

    expect($config->getDomain())->toBe('app.myproject.local');
});

test('generates correct domain with custom subdomain', function () {
    $config = new ProxyConfig(
        enabled: true,
        domainPrefix: 'myproject',
        certResolver: 'selfsigned',
        dashboard: true,
    );

    expect($config->getDomain('api'))->toBe('api.myproject.local')
        ->and($config->getDomain('mailpit'))->toBe('mailpit.myproject.local')
        ->and($config->getDomain('traefik'))->toBe('traefik.myproject.local');
});

test('ProxyConfig is immutable', function () {
    $config = new ProxyConfig(
        enabled: true,
        domainPrefix: 'myproject',
        certResolver: 'selfsigned',
        dashboard: true,
    );

    $reflection = new \ReflectionClass($config);
    expect($reflection->isReadOnly())->toBeTrue();
});

test('creates disabled ProxyConfig', function () {
    $config = ProxyConfig::disabled();

    expect($config->enabled)->toBeFalse()
        ->and($config->domainPrefix)->toBe('')
        ->and($config->certResolver)->toBe('')
        ->and($config->dashboard)->toBeFalse();
});
