<?php

declare(strict_types=1);

// ABOUTME: Parses proxy configuration section from YAML data.
// ABOUTME: Handles Traefik proxy settings parsing.

namespace Seaman\Service\ConfigParser;

use Seaman\Enum\DnsProvider;
use Seaman\ValueObject\ProxyConfig;

final readonly class ProxyConfigParser
{
    use ConfigDataExtractor;

    /**
     * @param array<string, mixed> $data
     */
    public function parse(array $data, string $projectName): ProxyConfig
    {
        $proxyData = $this->getArray($data, 'proxy');
        if ($proxyData === []) {
            return ProxyConfig::default($projectName);
        }

        /** @var array<string, mixed> $proxyData */
        $dnsProviderValue = $proxyData['dns_provider'] ?? null;
        $dnsProvider = is_string($dnsProviderValue) ? DnsProvider::tryFrom($dnsProviderValue) : null;

        return new ProxyConfig(
            enabled: $this->getBool($proxyData, 'enabled', true),
            domainPrefix: $this->getString($proxyData, 'domain_prefix', $projectName),
            certResolver: $this->getString($proxyData, 'cert_resolver', 'selfsigned'),
            dashboard: $this->getBool($proxyData, 'dashboard', true),
            dnsProvider: $dnsProvider,
        );
    }
}
