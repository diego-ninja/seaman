<?php

declare(strict_types=1);

// ABOUTME: Configuration for Traefik reverse proxy.
// ABOUTME: Manages domain prefix, certificate resolver, DNS provider, and dashboard settings.

namespace Seaman\ValueObject;

use Seaman\Enum\DnsProvider;

final readonly class ProxyConfig
{
    public function __construct(
        public bool $enabled,
        public string $domainPrefix,
        public string $certResolver,
        public bool $dashboard,
        public ?DnsProvider $dnsProvider = null,
    ) {}

    /**
     * Create default proxy configuration for a project.
     */
    public static function default(string $projectName): self
    {
        return new self(
            enabled: true,
            domainPrefix: $projectName,
            certResolver: 'selfsigned',
            dashboard: true,
        );
    }

    /**
     * Create disabled proxy configuration.
     */
    public static function disabled(): self
    {
        return new self(
            enabled: false,
            domainPrefix: '',
            certResolver: '',
            dashboard: false,
        );
    }

    /**
     * Get full domain for a subdomain.
     *
     * @param string $subdomain Subdomain (defaults to 'app')
     * @return string Full domain (e.g., "app.myproject.local")
     */
    public function getDomain(string $subdomain = 'app'): string
    {
        return "{$subdomain}.{$this->domainPrefix}.local";
    }
}
