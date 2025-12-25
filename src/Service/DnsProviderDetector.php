<?php

// ABOUTME: Detects available DNS providers on the current system.
// ABOUTME: Provides platform-specific detection for dnsmasq, systemd-resolved, NetworkManager, macOS resolver.

declare(strict_types=1);

namespace Seaman\Service;

use Seaman\Contract\CommandExecutor;
use Seaman\Enum\DnsProvider;
use Seaman\ValueObject\DetectedDnsProvider;

final readonly class DnsProviderDetector
{
    public function __construct(
        private CommandExecutor $executor,
    ) {}

    /**
     * Detect all available DNS providers on this system.
     *
     * @return list<DetectedDnsProvider>
     */
    public function detectAvailableProviders(string $projectName): array
    {
        /** @var list<DetectedDnsProvider> $providers */
        $providers = [];

        // macOS resolver (only on Darwin)
        if (PHP_OS_FAMILY === 'Darwin') {
            $providers[] = new DetectedDnsProvider(
                provider: DnsProvider::MacOSResolver,
                configPath: "/etc/resolver/{$projectName}.local",
                requiresSudo: true,
            );
        }

        // dnsmasq - only if it can actually run (port 53 available or already running)
        if ($this->hasDnsmasq() && $this->canUseDnsmasq()) {
            $providers[] = new DetectedDnsProvider(
                provider: DnsProvider::Dnsmasq,
                configPath: $this->getDnsmasqConfigPath($projectName),
                requiresSudo: true,
            );
        }

        // NetworkManager with dnsmasq plugin
        if ($this->hasNetworkManagerWithDnsmasq()) {
            $providers[] = new DetectedDnsProvider(
                provider: DnsProvider::NetworkManager,
                configPath: "/etc/NetworkManager/dnsmasq.d/seaman-{$projectName}.conf",
                requiresSudo: true,
            );
        }

        // systemd-resolved - but warn if stub listener is active
        if ($this->hasSystemdResolved() && !$this->hasSystemdResolvedStubListener()) {
            $providers[] = new DetectedDnsProvider(
                provider: DnsProvider::SystemdResolved,
                configPath: "/etc/systemd/resolved.conf.d/seaman-{$projectName}.conf",
                requiresSudo: true,
            );
        }

        // /etc/hosts is always available as fallback
        $providers[] = new DetectedDnsProvider(
            provider: DnsProvider::HostsFile,
            configPath: '/etc/hosts',
            requiresSudo: true,
        );

        // Sort by priority
        usort(
            $providers,
            fn(DetectedDnsProvider $a, DetectedDnsProvider $b): int
            => $a->provider->getPriority() <=> $b->provider->getPriority(),
        );

        return $providers;
    }

    /**
     * Get the recommended DNS provider for this system.
     */
    public function getRecommendedProvider(string $projectName): ?DetectedDnsProvider
    {
        $providers = $this->detectAvailableProviders($projectName);

        return $providers[0] ?? null;
    }

    /**
     * Check if dnsmasq is available on the system.
     */
    public function hasDnsmasq(): bool
    {
        $result = $this->executor->execute(['which', 'dnsmasq']);

        return $result->isSuccessful();
    }

    /**
     * Check if systemd-resolved is active on the system.
     */
    public function hasSystemdResolved(): bool
    {
        $result = $this->executor->execute(['systemctl', 'is-active', 'systemd-resolved']);

        return $result->isSuccessful();
    }

    /**
     * Check if NetworkManager is active on the system.
     */
    public function hasNetworkManager(): bool
    {
        $result = $this->executor->execute(['systemctl', 'is-active', 'NetworkManager']);
        if (!$result->isSuccessful()) {
            return false;
        }

        return is_dir('/etc/NetworkManager');
    }

    /**
     * Check if NetworkManager is configured to use dnsmasq.
     */
    public function hasNetworkManagerWithDnsmasq(): bool
    {
        if (!$this->hasNetworkManager()) {
            return false;
        }

        // Check if dnsmasq plugin is enabled in NetworkManager
        $nmConfPath = '/etc/NetworkManager/NetworkManager.conf';
        if (!file_exists($nmConfPath)) {
            return false;
        }

        $content = file_get_contents($nmConfPath);
        if ($content === false) {
            return false;
        }

        return str_contains($content, 'dns=dnsmasq');
    }

    /**
     * Check if dnsmasq can actually be used (port 53 available or dnsmasq already running).
     */
    public function canUseDnsmasq(): bool
    {
        // Check if dnsmasq is already running
        $result = $this->executor->execute(['systemctl', 'is-active', 'dnsmasq']);
        if ($result->isSuccessful()) {
            return true;
        }

        // Check if port 53 is available (not blocked by systemd-resolved stub)
        return !$this->isPort53Occupied();
    }

    /**
     * Check if port 53 is occupied by something other than dnsmasq.
     */
    public function isPort53Occupied(): bool
    {
        // Check if systemd-resolved stub listener is active on 127.0.0.53:53
        $result = $this->executor->execute(['ss', '-tlnp']);
        if (!$result->isSuccessful()) {
            return false;
        }

        $output = $result->output;

        // If something is listening on 127.0.0.53:53 or 127.0.0.1:53, port is occupied
        return str_contains($output, '127.0.0.53:53') || str_contains($output, '127.0.0.1:53');
    }

    /**
     * Check if systemd-resolved stub listener is active (blocking port 53).
     */
    public function hasSystemdResolvedStubListener(): bool
    {
        if (!$this->hasSystemdResolved()) {
            return false;
        }

        return $this->isPort53Occupied();
    }

    /**
     * Get dnsmasq configuration path based on platform.
     */
    public function getDnsmasqConfigPath(string $projectName): string
    {
        return match (PHP_OS_FAMILY) {
            'Linux' => "/etc/dnsmasq.d/seaman-{$projectName}.conf",
            'Darwin' => "/usr/local/etc/dnsmasq.d/seaman-{$projectName}.conf",
            default => throw new \RuntimeException('Unsupported platform: ' . PHP_OS_FAMILY),
        };
    }
}
