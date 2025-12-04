<?php

declare(strict_types=1);

// ABOUTME: Traefik reverse proxy service implementation.
// ABOUTME: Configures Traefik with HTTPS, automatic routing, and dashboard.

namespace Seaman\Service\Container;

use Seaman\Enum\Service;
use Seaman\ValueObject\HealthCheck;
use Seaman\ValueObject\ServiceConfig;

readonly class TraefikService extends AbstractService
{
    public function getType(): Service
    {
        return Service::Traefik;
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: $this->getType()->value,
            enabled: true, // Traefik is always enabled
            type: $this->getType(),
            version: 'v3.1',
            port: $this->getType()->port(),
            additionalPorts: [80, 8080],
            environmentVariables: [],
        );
    }

    public function generateComposeConfig(ServiceConfig $config): array
    {
        return [
            'image' => "traefik:{$config->version}",
            'ports' => [
                '80:80',      // HTTP
                '443:443',    // HTTPS
                '8080:8080',  // Dashboard
            ],
            'volumes' => [
                '/var/run/docker.sock:/var/run/docker.sock:ro',
                './.seaman/traefik:/etc/traefik',
                './.seaman/certs:/certs:ro',
            ],
            'command' => [
                '--api.dashboard=true',
                '--api.insecure=true',
                '--providers.docker=true',
                '--providers.docker.exposedbydefault=false',
                '--providers.file.directory=/etc/traefik/dynamic',
                '--entrypoints.web.address=:80',
                '--entrypoints.websecure.address=:443',
                '--log.level=INFO',
                '--accesslog=true',
            ],
            'labels' => [
                'traefik.enable=true',
                'traefik.http.routers.traefik.rule=Host(`traefik.${PROJECT_NAME}.local`)',
                'traefik.http.routers.traefik.service=api@internal',
                'traefik.http.routers.traefik.entrypoints=websecure',
                'traefik.http.routers.traefik.tls=true',
            ],
            'networks' => ['seaman'],
        ];
    }

    public function getRequiredPorts(): array
    {
        return [80, 443, 8080];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return null; // Traefik doesn't need a health check
    }

    /**
     * @return array<string, string|int>
     */
    public function getEnvVariables(ServiceConfig $config): array
    {
        return [];
    }
}
