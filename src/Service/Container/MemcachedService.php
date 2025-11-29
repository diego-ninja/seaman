<?php

declare(strict_types=1);

// ABOUTME: Memcached cache service implementation.
// ABOUTME: Configures Memcached container for caching.

namespace Seaman\Service\Container;

use Seaman\Enum\Service;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

readonly class MemcachedService implements ServiceInterface
{
    public function getName(): string
    {
        return Service::Memcached->value;
    }

    public function getDisplayName(): string
    {
        return Service::Memcached->name;
    }

    public function getDescription(): string
    {
        return 'Memcached cache storage';
    }

    /**
     * @return list<string>
     */
    public function getDependencies(): array
    {
        return [];
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: Service::Memcached->value,
            enabled: false,
            type: 'memcached',
            version: '1.6-alpine',
            port: 11211,
            additionalPorts: [],
            environmentVariables: [],
        );
    }

    /**
     * @param ServiceConfig $config
     * @return array<string, mixed>
     */
    public function generateComposeConfig(ServiceConfig $config): array
    {
        $composeConfig = [
            'image' => "memcached:{$config->version}",
            'ports' => ['${MEMCACHED_PORT}:11211'],
            'networks' => ['seaman'],
        ];

        return $composeConfig;
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
}
