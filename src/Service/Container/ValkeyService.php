<?php

declare(strict_types=1);

// ABOUTME: Valkey cache service implementation.
// ABOUTME: Configures Valkey (Redis fork) for caching and sessions.

namespace Seaman\Service\Container;

use Seaman\Enum\Service;
use Seaman\ValueObject\HealthCheck;
use Seaman\ValueObject\ServiceConfig;

readonly class ValkeyService extends AbstractService
{
    public function getType(): Service
    {
        return Service::Valkey;
    }

    public function getIcon(): string
    {
        return '🧵';
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: $this->getType()->value,
            enabled: false,
            type: $this->getType(),
            version: '8-alpine',
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
            'image' => 'valkey/valkey:' . $config->version,
            'ports' => ['${VALKEY_PORT}:6379'],
            'networks' => ['seaman'],
        ];

        return $this->addHealthCheckToConfig($composeConfig);
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
            test: ['CMD', 'valkey-cli', 'ping'],
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
            'VALKEY_PORT' => $config->port,
            'REDIS_URL' => 'redis://valkey:6379',
        ];
    }
}
