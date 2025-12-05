<?php

declare(strict_types=1);

// ABOUTME: Tests for DetectedDnsProvider value object.
// ABOUTME: Validates detected DNS provider properties.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\Enum\DnsProvider;
use Seaman\ValueObject\DetectedDnsProvider;

test('creates DetectedDnsProvider with all properties', function () {
    $detected = new DetectedDnsProvider(
        provider: DnsProvider::Dnsmasq,
        configPath: '/etc/dnsmasq.d/seaman-test.conf',
        requiresSudo: true,
    );

    expect($detected->provider)->toBe(DnsProvider::Dnsmasq)
        ->and($detected->configPath)->toBe('/etc/dnsmasq.d/seaman-test.conf')
        ->and($detected->requiresSudo)->toBeTrue();
});

test('DetectedDnsProvider is immutable', function () {
    $detected = new DetectedDnsProvider(
        provider: DnsProvider::MacOSResolver,
        configPath: '/etc/resolver/test.local',
        requiresSudo: true,
    );

    $reflection = new \ReflectionClass($detected);
    expect($reflection->isReadOnly())->toBeTrue();
});
