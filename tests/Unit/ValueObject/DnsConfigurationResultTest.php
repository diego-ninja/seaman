<?php

declare(strict_types=1);

// ABOUTME: Tests for DnsConfigurationResult value object.
// ABOUTME: Validates DNS configuration result properties.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\ValueObject\DnsConfigurationResult;

test('creates DnsConfigurationResult with automatic configuration', function () {
    $result = new DnsConfigurationResult(
        type: 'dnsmasq',
        automatic: true,
        requiresSudo: true,
        configPath: '/etc/dnsmasq.d/seaman-myproject.conf',
        configContent: 'address=/.myproject.local/127.0.0.1',
        instructions: [],
    );

    expect($result->type)->toBe('dnsmasq')
        ->and($result->automatic)->toBeTrue()
        ->and($result->requiresSudo)->toBeTrue()
        ->and($result->configPath)->toBe('/etc/dnsmasq.d/seaman-myproject.conf')
        ->and($result->configContent)->toBe('address=/.myproject.local/127.0.0.1')
        ->and($result->instructions)->toBeEmpty();
});

test('creates DnsConfigurationResult with manual instructions', function () {
    $instructions = [
        'Add the following to /etc/hosts:',
        '127.0.0.1 app.myproject.local',
        '127.0.0.1 traefik.myproject.local',
    ];

    $result = new DnsConfigurationResult(
        type: 'manual',
        automatic: false,
        requiresSudo: false,
        configPath: null,
        configContent: null,
        instructions: $instructions,
    );

    expect($result->type)->toBe('manual')
        ->and($result->automatic)->toBeFalse()
        ->and($result->requiresSudo)->toBeFalse()
        ->and($result->configPath)->toBeNull()
        ->and($result->configContent)->toBeNull()
        ->and($result->instructions)->toHaveCount(3);
});

test('creates systemd-resolved configuration result', function () {
    $result = new DnsConfigurationResult(
        type: 'systemd-resolved',
        automatic: true,
        requiresSudo: true,
        configPath: '/etc/systemd/resolved.conf.d/seaman-myproject.conf',
        configContent: '[Resolve]' . "\n" . 'DNS=127.0.0.1' . "\n" . 'Domains=~myproject.local',
        instructions: [],
    );

    expect($result->type)->toBe('systemd-resolved')
        ->and($result->automatic)->toBeTrue()
        ->and($result->requiresSudo)->toBeTrue();
});

test('DnsConfigurationResult is immutable', function () {
    $result = new DnsConfigurationResult(
        type: 'dnsmasq',
        automatic: true,
        requiresSudo: true,
        configPath: '/etc/dnsmasq.d/test.conf',
        configContent: 'test',
        instructions: [],
    );

    $reflection = new \ReflectionClass($result);
    expect($reflection->isReadOnly())->toBeTrue();
});
