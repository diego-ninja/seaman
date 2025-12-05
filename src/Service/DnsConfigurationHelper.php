<?php

declare(strict_types=1);

// ABOUTME: Helps configure DNS for local development domains.
// ABOUTME: Detects platform capabilities and provides setup instructions.

namespace Seaman\Service;

use Seaman\Contract\CommandExecutor;
use Seaman\Enum\DnsProvider;
use Seaman\ValueObject\DetectedDnsProvider;
use Seaman\ValueObject\DnsConfigurationResult;

final readonly class DnsConfigurationHelper
{
    public function __construct(
        private CommandExecutor $executor,
    ) {}

    /**
     * Configure DNS for the project (legacy method for backward compatibility).
     */
    public function configure(string $projectName): DnsConfigurationResult
    {
        $recommended = $this->getRecommendedProvider($projectName);

        if ($recommended === null) {
            return $this->getManualInstructions($projectName);
        }

        return $this->configureProvider($projectName, $recommended->provider);
    }

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

        // dnsmasq
        if ($this->hasDnsmasq()) {
            $providers[] = new DetectedDnsProvider(
                provider: DnsProvider::Dnsmasq,
                configPath: $this->getDnsmasqConfigPath($projectName),
                requiresSudo: true,
            );
        }

        // systemd-resolved
        if ($this->hasSystemdResolved()) {
            $providers[] = new DetectedDnsProvider(
                provider: DnsProvider::SystemdResolved,
                configPath: "/etc/systemd/resolved.conf.d/seaman-{$projectName}.conf",
                requiresSudo: true,
            );
        }

        // NetworkManager
        if ($this->hasNetworkManager()) {
            $providers[] = new DetectedDnsProvider(
                provider: DnsProvider::NetworkManager,
                configPath: "/etc/NetworkManager/dnsmasq.d/seaman-{$projectName}.conf",
                requiresSudo: true,
            );
        }

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
     * Configure DNS using a specific provider.
     */
    public function configureProvider(string $projectName, DnsProvider $provider): DnsConfigurationResult
    {
        return match ($provider) {
            DnsProvider::Dnsmasq => $this->configureDnsmasq($projectName),
            DnsProvider::SystemdResolved => $this->configureSystemdResolved($projectName),
            DnsProvider::NetworkManager => $this->configureNetworkManager($projectName),
            DnsProvider::MacOSResolver => $this->configureMacOSResolver($projectName),
            DnsProvider::Manual => $this->getManualInstructions($projectName),
        };
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
     * Configure DNS using dnsmasq.
     */
    private function configureDnsmasq(string $projectName): DnsConfigurationResult
    {
        $configPath = $this->getDnsmasqConfigPath($projectName);
        $configContent = "address=/.{$projectName}.local/127.0.0.1\n";

        $restartCommand = PHP_OS_FAMILY === 'Darwin'
            ? 'sudo brew services restart dnsmasq'
            : 'sudo systemctl restart dnsmasq';

        return new DnsConfigurationResult(
            type: 'dnsmasq',
            automatic: true,
            requiresSudo: true,
            configPath: $configPath,
            configContent: $configContent,
            instructions: [],
            restartCommand: $restartCommand,
        );
    }

    /**
     * Configure DNS using systemd-resolved.
     */
    private function configureSystemdResolved(string $projectName): DnsConfigurationResult
    {
        $configPath = "/etc/systemd/resolved.conf.d/seaman-{$projectName}.conf";
        $configContent = "[Resolve]\n";
        $configContent .= "DNS=127.0.0.1\n";
        $configContent .= "Domains=~{$projectName}.local\n";

        return new DnsConfigurationResult(
            type: 'systemd-resolved',
            automatic: true,
            requiresSudo: true,
            configPath: $configPath,
            configContent: $configContent,
            instructions: [],
            restartCommand: 'sudo systemctl restart systemd-resolved',
        );
    }

    /**
     * Configure DNS using NetworkManager with dnsmasq.
     */
    private function configureNetworkManager(string $projectName): DnsConfigurationResult
    {
        $configPath = "/etc/NetworkManager/dnsmasq.d/seaman-{$projectName}.conf";
        $configContent = "address=/.{$projectName}.local/127.0.0.1\n";

        return new DnsConfigurationResult(
            type: 'networkmanager',
            automatic: true,
            requiresSudo: true,
            configPath: $configPath,
            configContent: $configContent,
            instructions: [],
            restartCommand: 'sudo systemctl restart NetworkManager',
        );
    }

    /**
     * Configure DNS using macOS resolver.
     */
    private function configureMacOSResolver(string $projectName): DnsConfigurationResult
    {
        $configPath = "/etc/resolver/{$projectName}.local";
        $configContent = "nameserver 127.0.0.1\n";

        return new DnsConfigurationResult(
            type: 'macos-resolver',
            automatic: true,
            requiresSudo: true,
            configPath: $configPath,
            configContent: $configContent,
            instructions: [],
            restartCommand: null, // macOS resolver doesn't need restart
        );
    }

    /**
     * Get manual instructions for DNS configuration.
     */
    private function getManualInstructions(string $projectName): DnsConfigurationResult
    {
        $instructions = [
            'Add the following entries to /etc/hosts:',
            '',
            "127.0.0.1 app.{$projectName}.local",
            "127.0.0.1 traefik.{$projectName}.local",
            "127.0.0.1 mailpit.{$projectName}.local",
            '',
            'Or install dnsmasq for wildcard domain support:',
            '  - macOS: brew install dnsmasq',
            '  - Ubuntu/Debian: apt-get install dnsmasq',
        ];

        return new DnsConfigurationResult(
            type: 'manual',
            automatic: false,
            requiresSudo: false,
            configPath: null,
            configContent: null,
            instructions: $instructions,
            restartCommand: null,
        );
    }

    /**
     * Get dnsmasq configuration path based on platform.
     */
    private function getDnsmasqConfigPath(string $projectName): string
    {
        return match (PHP_OS_FAMILY) {
            'Linux' => "/etc/dnsmasq.d/seaman-{$projectName}.conf",
            'Darwin' => "/usr/local/etc/dnsmasq.d/seaman-{$projectName}.conf",
            default => throw new \RuntimeException('Unsupported platform: ' . PHP_OS_FAMILY),
        };
    }
}
