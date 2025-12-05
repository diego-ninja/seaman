<?php

declare(strict_types=1);

// ABOUTME: Helps configure DNS for local development domains.
// ABOUTME: Detects platform capabilities and provides setup instructions.

namespace Seaman\Service;

use Seaman\Contract\CommandExecutor;
use Seaman\ValueObject\DnsConfigurationResult;

final readonly class DnsConfigurationHelper
{
    public function __construct(
        private CommandExecutor $executor,
    ) {}

    /**
     * Configure DNS for the project.
     */
    public function configure(string $projectName): DnsConfigurationResult
    {
        // Check for dnsmasq
        if ($this->hasDnsmasq()) {
            return $this->configureDnsmasq($projectName);
        }

        // Check for systemd-resolved (Linux)
        if ($this->hasSystemdResolved()) {
            return $this->configureSystemdResolved($projectName);
        }

        // Fallback to manual instructions
        return $this->getManualInstructions($projectName);
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
     * Configure DNS using dnsmasq.
     */
    private function configureDnsmasq(string $projectName): DnsConfigurationResult
    {
        $configPath = $this->getDnsmasqConfigPath($projectName);
        $configContent = "address=/.{$projectName}.local/127.0.0.1\n";

        return new DnsConfigurationResult(
            type: 'dnsmasq',
            automatic: true,
            requiresSudo: true,
            configPath: $configPath,
            configContent: $configContent,
            instructions: [],
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
