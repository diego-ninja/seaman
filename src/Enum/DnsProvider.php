<?php

declare(strict_types=1);

// ABOUTME: Enum representing available DNS configuration providers.
// ABOUTME: Includes priority ordering for automatic provider selection.

namespace Seaman\Enum;

enum DnsProvider: string
{
    case Dnsmasq = 'dnsmasq';
    case SystemdResolved = 'systemd-resolved';
    case NetworkManager = 'networkmanager';
    case MacOSResolver = 'macos-resolver';
    case HostsFile = 'hosts-file';
    case Manual = 'manual';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::Dnsmasq => 'dnsmasq',
            self::SystemdResolved => 'systemd-resolved',
            self::NetworkManager => 'NetworkManager',
            self::MacOSResolver => 'macOS Resolver',
            self::HostsFile => '/etc/hosts',
            self::Manual => 'Manual',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Dnsmasq => 'Lightweight DNS forwarder with wildcard support',
            self::SystemdResolved => 'Systemd network name resolution manager',
            self::NetworkManager => 'NetworkManager with dnsmasq plugin',
            self::MacOSResolver => 'macOS native resolver for custom domains',
            self::HostsFile => 'Simple /etc/hosts entries (no wildcard support)',
            self::Manual => 'Show manual configuration instructions',
        };
    }

    /**
     * Get priority for automatic selection (lower = higher priority).
     */
    public function getPriority(): int
    {
        return match ($this) {
            self::MacOSResolver => 1,
            self::Dnsmasq => 2,
            self::NetworkManager => 3,
            self::SystemdResolved => 4,
            self::HostsFile => 5,
            self::Manual => 99,
        };
    }
}
