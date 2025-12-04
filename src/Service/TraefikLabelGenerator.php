<?php

declare(strict_types=1);

// ABOUTME: Generates Traefik labels for docker-compose services.
// ABOUTME: Handles routing, TLS, and service port configuration.

namespace Seaman\Service;

use Seaman\Enum\Service;
use Seaman\Enum\ServiceExposureType;
use Seaman\ValueObject\ProxyConfig;
use Seaman\ValueObject\ServiceConfig;

final readonly class TraefikLabelGenerator
{
    /**
     * Generate Traefik labels for a service.
     *
     * @return list<string> Traefik labels for docker-compose
     */
    public function generateLabels(ServiceConfig $service, ProxyConfig $proxy): array
    {
        $exposureType = $this->getExposureType($service->type);

        // DirectPort services don't use Traefik
        if ($exposureType === ServiceExposureType::DirectPort) {
            return ['traefik.enable=false'];
        }

        // ProxyOnly services get full Traefik configuration
        $serviceName = $service->name;
        $domain = $proxy->getDomain($serviceName);
        $routerName = str_replace('.', '-', $serviceName);

        return [
            'traefik.enable=true',
            "traefik.http.routers.{$routerName}.rule=Host(`{$domain}`)",
            "traefik.http.routers.{$routerName}.entrypoints=websecure",
            "traefik.http.routers.{$routerName}.tls=true",
            $this->getServiceLabel($service),
        ];
    }

    /**
     * Determine how a service should be exposed.
     */
    private function getExposureType(Service $service): ServiceExposureType
    {
        return match ($service) {
            // Web UIs - accessible through Traefik
            Service::App,
            Service::Mailpit,
            Service::RabbitMq,
            Service::Dozzle,
            Service::MinIO,
            Service::Elasticsearch,
            Service::Traefik => ServiceExposureType::ProxyOnly,

            // Data services - need direct port access
            Service::PostgreSQL,
            Service::MySQL,
            Service::MariaDB,
            Service::MongoDB,
            Service::Redis,
            Service::Memcached,
            Service::Kafka,
            Service::SQLite,
            Service::None => ServiceExposureType::DirectPort,
        };
    }

    /**
     * Get the Traefik service label with correct port mapping.
     */
    private function getServiceLabel(ServiceConfig $service): string
    {
        $port = match ($service->type) {
            Service::Mailpit => 8025,    // UI port
            Service::RabbitMq => 15672,  // Management UI port
            Service::Dozzle => 8080,     // UI port
            Service::MinIO => 9001,      // Console port
            default => 80,               // Default HTTP port
        };

        return "traefik.http.services.{$service->name}.loadbalancer.server.port={$port}";
    }
}
