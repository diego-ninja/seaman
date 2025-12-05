<?php

declare(strict_types=1);

// ABOUTME: Memcached cache service implementation.
// ABOUTME: Configures Memcached container for caching.

namespace Seaman\Service\Container;

use Seaman\Enum\Service;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

readonly class MemcachedService extends AbstractService
{
    public function getType(): Service
    {
        return Service::Memcached;
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: $this->getType()->value,
            enabled: false,
            type: $this->getType(),
            version: '1.6-alpine',
            port: $this->getType()->port(),
            additionalPorts: [],
            environmentVariables: [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function generateComposeConfig(ServiceConfig $config): array
    {
        $composeConfig = [
            'image' => "memcached:{$config->version}",
            'ports' => ['${MEMCACHED_PORT}:11211'],
            'networks' => ['seaman'],
        ];

        return $this->addHealthCheckToConfig($composeConfig);
    }

    /**
     * @return list<int>
     */
    public function getRequiredPorts(): array
    {
        return [11211];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return null;
    }

    /**
     * @return array<string, string|int>
     */
    public function getEnvVariables(ServiceConfig $config): array
    {
        return [
            'MEMCACHED_PORT' => $config->port,
        ];
    }
}
