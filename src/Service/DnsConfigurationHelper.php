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
     * Configure DNS using a specific provider.
     */
    public function configureProvider(string $projectName, DnsProvider $provider): DnsConfigurationResult
    {
        return match ($provider) {
            DnsProvider::Dnsmasq => $this->configureDnsmasq($projectName),
            DnsProvider::SystemdResolved => $this->configureSystemdResolved($projectName),
            DnsProvider::NetworkManager => $this->configureNetworkManager($projectName),
            DnsProvider::MacOSResolver => $this->configureMacOSResolver($projectName),
            DnsProvider::HostsFile => $this->configureHostsFile($projectName),
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
    private function isPort53Occupied(): bool
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
     * Configure DNS using /etc/hosts file.
     */
    private function configureHostsFile(string $projectName): DnsConfigurationResult
    {
        $hostsEntries = [
            "app.{$projectName}.local",
            "traefik.{$projectName}.local",
            "mailpit.{$projectName}.local",
            "dozzle.{$projectName}.local",
        ];

        $configContent = "\n# Seaman - {$projectName}\n";
        foreach ($hostsEntries as $host) {
            $configContent .= "127.0.0.1 {$host}\n";
        }

        return new DnsConfigurationResult(
            type: 'hosts-file',
            automatic: true,
            requiresSudo: true,
            configPath: '/etc/hosts',
            configContent: $configContent,
            instructions: [],
            restartCommand: null,
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
            'Or configure dnsmasq for wildcard domain support:',
            '',
            'On systems with systemd-resolved blocking port 53:',
            '  1. Disable stub listener: sudo mkdir -p /etc/systemd/resolved.conf.d',
            "  2. Create config: echo -e '[Resolve]\\nDNSStubListener=no' | sudo tee /etc/systemd/resolved.conf.d/no-stub.conf",
            '  3. Restart resolved: sudo systemctl restart systemd-resolved',
            '  4. Start dnsmasq: sudo systemctl enable --now dnsmasq',
            '',
            'Or use NetworkManager with dnsmasq:',
            "  1. Add 'dns=dnsmasq' to [main] section in /etc/NetworkManager/NetworkManager.conf",
            '  2. Restart NetworkManager: sudo systemctl restart NetworkManager',
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
