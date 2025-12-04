<?php

declare(strict_types=1);

// ABOUTME: Tests for TraefikService implementation.
// ABOUTME: Validates Traefik reverse proxy service configuration.

namespace Seaman\Tests\Unit\Service\Container;

use Seaman\Enum\Service;
use Seaman\Service\Container\TraefikService;
use Seaman\Service\Container\ServiceInterface;
use Seaman\ValueObject\ServiceConfig;

test('TraefikService implements ServiceInterface', function () {
    $service = new TraefikService();

    expect($service)->toBeInstanceOf(ServiceInterface::class);
});

test('TraefikService returns correct name', function () {
    $service = new TraefikService();

    expect($service->getName())->toBe('traefik');
});

test('TraefikService returns correct display name', function () {
    $service = new TraefikService();

    expect($service->getDisplayName())->toBe('Traefik');
});

test('TraefikService returns correct description', function () {
    $service = new TraefikService();

    expect($service->getDescription())->toBe('Traefik reverse proxy with HTTPS support');
});

test('TraefikService has no dependencies', function () {
    $service = new TraefikService();

    expect($service->getDependencies())->toBe([]);
});

test('TraefikService returns default config with correct values', function () {
    $service = new TraefikService();
    $config = $service->getDefaultConfig();

    expect($config)->toBeInstanceOf(ServiceConfig::class)
        ->and($config->name)->toBe('traefik')
        ->and($config->enabled)->toBe(true) // Always enabled
        ->and($config->type)->toBe(Service::Traefik)
        ->and($config->version)->toBe('v3.1')
        ->and($config->port)->toBe(443)
        ->and($config->additionalPorts)->toBe([80, 8080])
        ->and($config->environmentVariables)->toBe([]);
});

test('TraefikService returns required ports', function () {
    $service = new TraefikService();

    expect($service->getRequiredPorts())->toBe([80, 443, 8080]);
});

test('TraefikService generates docker compose config', function () {
    $service = new TraefikService();
    $config = new ServiceConfig(
        name: 'traefik',
        enabled: true,
        type: Service::Traefik,
        version: 'v3.1',
        port: 443,
        additionalPorts: [80, 8080],
        environmentVariables: [],
    );

    $compose = $service->generateComposeConfig($config);

    expect($compose)->toHaveKey('image')
        ->and($compose['image'])->toBe('traefik:v3.1')
        ->and($compose)->toHaveKey('ports')
        ->and($compose['ports'])->toContain('80:80')
        ->and($compose['ports'])->toContain('443:443')
        ->and($compose['ports'])->toContain('8080:8080')
        ->and($compose)->toHaveKey('volumes')
        ->and($compose['volumes'])->toContain('/var/run/docker.sock:/var/run/docker.sock:ro')
        ->and($compose['volumes'])->toContain('./.seaman/traefik:/etc/traefik')
        ->and($compose['volumes'])->toContain('./.seaman/certs:/certs:ro')
        ->and($compose)->toHaveKey('command')
        ->and($compose['command'])->toContain('--api.dashboard=true')
        ->and($compose['command'])->toContain('--providers.docker=true')
        ->and($compose['command'])->toContain('--entrypoints.web.address=:80')
        ->and($compose['command'])->toContain('--entrypoints.websecure.address=:443')
        ->and($compose)->toHaveKey('labels')
        ->and($compose)->toHaveKey('networks');
});

test('TraefikService env variables are empty', function () {
    $service = new TraefikService();
    $config = new ServiceConfig(
        name: 'traefik',
        enabled: true,
        type: Service::Traefik,
        version: 'v3.1',
        port: 443,
        additionalPorts: [80, 8080],
        environmentVariables: [],
    );

    $envVars = $service->getEnvVariables($config);

    expect($envVars)->toBe([]);
});
