<?php

declare(strict_types=1);

// ABOUTME: Redis cache service implementation.
// ABOUTME: Configures Redis container for caching and sessions.

namespace Seaman\Service\Container;

use Seaman\Enum\Service;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

readonly class RedisService extends AbstractService
{
    public function getType(): Service
    {
        return Service::Redis;
    }

    /**
     * @return list<string>
     */
    public function getDependencies(): array
    {
        return [];
    }

    public function getIcon(): string
    {
        return '🧵';
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: $this->getType()->value,
            enabled: true,
            type: $this->getType(),
            version: '7-alpine',
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
        $healthCheck = $this->getHealthCheck();
        $composeConfig = [
            'image' => "redis:{$config->version}",
            'ports' => ['${REDIS_PORT}:6379'],
            'networks' => ['seaman'],
        ];

        if ($healthCheck !== null) {
            $composeConfig['healthcheck'] = [
                'test' => $healthCheck->test,
                'interval' => $healthCheck->interval,
                'timeout' => $healthCheck->timeout,
                'retries' => $healthCheck->retries,
            ];
        }

        return $composeConfig;
    }

    /**
     * @return list<int>
     */
    public function getRequiredPorts(): array
    {
        return [6379];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return new HealthCheck(
            test: ['CMD', 'redis-cli', 'ping'],
            interval: '10s',
            timeout: '5s',
            retries: 5,
        );
    }

    /**
     * @return array<string, string|int>
     */
    public function getEnvVariables(ServiceConfig $config): array
    {
        return [
            'REDIS_PORT' => $config->port,
        ];
    }
}
