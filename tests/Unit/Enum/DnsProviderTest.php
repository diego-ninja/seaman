<?php

declare(strict_types=1);

// ABOUTME: Tests for DnsProvider enum.
// ABOUTME: Validates DNS provider properties and priority ordering.

namespace Seaman\Tests\Unit\Enum;

use Seaman\Enum\DnsProvider;

test('DnsProvider has all expected cases', function () {
    $cases = DnsProvider::cases();

    expect($cases)->toHaveCount(5)
        ->and(DnsProvider::Dnsmasq->value)->toBe('dnsmasq')
        ->and(DnsProvider::SystemdResolved->value)->toBe('systemd-resolved')
        ->and(DnsProvider::NetworkManager->value)->toBe('networkmanager')
        ->and(DnsProvider::MacOSResolver->value)->toBe('macos-resolver')
        ->and(DnsProvider::Manual->value)->toBe('manual');
});

test('DnsProvider has display names', function () {
    expect(DnsProvider::Dnsmasq->getDisplayName())->toBe('dnsmasq')
        ->and(DnsProvider::SystemdResolved->getDisplayName())->toBe('systemd-resolved')
        ->and(DnsProvider::NetworkManager->getDisplayName())->toBe('NetworkManager')
        ->and(DnsProvider::MacOSResolver->getDisplayName())->toBe('macOS Resolver')
        ->and(DnsProvider::Manual->getDisplayName())->toBe('Manual');
});

test('DnsProvider has descriptions', function () {
    expect(DnsProvider::Dnsmasq->getDescription())->toBeString()
        ->and(DnsProvider::MacOSResolver->getDescription())->toContain('native');
});

test('DnsProvider has correct priorities', function () {
    expect(DnsProvider::MacOSResolver->getPriority())->toBe(1)
        ->and(DnsProvider::Dnsmasq->getPriority())->toBe(2)
        ->and(DnsProvider::SystemdResolved->getPriority())->toBe(3)
        ->and(DnsProvider::NetworkManager->getPriority())->toBe(4)
        ->and(DnsProvider::Manual->getPriority())->toBe(99);
});

test('DnsProvider sorts by priority correctly', function () {
    $providers = [
        DnsProvider::Manual,
        DnsProvider::Dnsmasq,
        DnsProvider::MacOSResolver,
        DnsProvider::NetworkManager,
    ];

    usort($providers, fn($a, $b) => $a->getPriority() <=> $b->getPriority());

    expect($providers[0])->toBe(DnsProvider::MacOSResolver)
        ->and($providers[1])->toBe(DnsProvider::Dnsmasq)
        ->and($providers[2])->toBe(DnsProvider::NetworkManager)
        ->and($providers[3])->toBe(DnsProvider::Manual);
});
