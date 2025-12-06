<?php

declare(strict_types=1);

// ABOUTME: Parses proxy configuration section from YAML data.
// ABOUTME: Handles Traefik proxy settings parsing.

namespace Seaman\Service\ConfigParser;

use Seaman\Enum\DnsProvider;
use Seaman\ValueObject\ProxyConfig;

final readonly class ProxyConfigParser
{
    /**
     * @param array<string, mixed> $data
     */
    public function parse(array $data, string $projectName): ProxyConfig
    {
        $proxyData = $data['proxy'] ?? [];
        if (!is_array($proxyData)) {
            return ProxyConfig::default($projectName);
        }

        $enabled = $proxyData['enabled'] ?? true;
        if (!is_bool($enabled)) {
            $enabled = true;
        }

        $domainPrefix = $proxyData['domain_prefix'] ?? $projectName;
        if (!is_string($domainPrefix)) {
            $domainPrefix = $projectName;
        }

        $certResolver = $proxyData['cert_resolver'] ?? 'selfsigned';
        if (!is_string($certResolver)) {
            $certResolver = 'selfsigned';
        }

        $dashboard = $proxyData['dashboard'] ?? true;
        if (!is_bool($dashboard)) {
            $dashboard = true;
        }

        $dnsProviderValue = $proxyData['dns_provider'] ?? null;
        $dnsProvider = is_string($dnsProviderValue) ? DnsProvider::tryFrom($dnsProviderValue) : null;

        return new ProxyConfig(
            enabled: $enabled,
            domainPrefix: $domainPrefix,
            certResolver: $certResolver,
            dashboard: $dashboard,
            dnsProvider: $dnsProvider,
        );
    }
}
