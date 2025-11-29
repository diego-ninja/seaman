<?php

declare(strict_types=1);

// ABOUTME: Redis cache service implementation.
// ABOUTME: Configures Redis container for caching and sessions.

namespace Seaman\Service\Container;

use Seaman\Enum\Service;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

readonly class RedisService implements ServiceInterface
{
    public function getName(): string
    {
        return Service::Redis->value;
    }

    public function getDisplayName(): string
    {
        return Service::Redis->name;
    }

    public function getDescription(): string
    {
        return 'Redis cache and session storage';
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
            name: Service::Redis->value,
            enabled: true,
            type: 'redis',
            version: '7-alpine',
            port: 6379,
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
}
