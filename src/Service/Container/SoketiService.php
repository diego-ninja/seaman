<?php

declare(strict_types=1);

// ABOUTME: Soketi WebSocket server service implementation.
// ABOUTME: Configures Soketi as Pusher-compatible WebSocket server.

namespace Seaman\Service\Container;

use Seaman\Enum\Service;
use Seaman\ValueObject\HealthCheck;
use Seaman\ValueObject\ServiceConfig;

readonly class SoketiService extends AbstractService
{
    public function getType(): Service
    {
        return Service::Soketi;
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: $this->getType()->value,
            enabled: false,
            type: $this->getType(),
            version: 'latest-16-alpine',
            port: $this->getType()->port(),
            additionalPorts: [9601],
            environmentVariables: [
                'SOKETI_DEBUG' => '1',
                'SOKETI_DEFAULT_APP_ID' => 'app-id',
                'SOKETI_DEFAULT_APP_KEY' => 'app-key',
                'SOKETI_DEFAULT_APP_SECRET' => 'app-secret',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function generateComposeConfig(ServiceConfig $config): array
    {
        $composeConfig = [
            'image' => 'quay.io/soketi/soketi:' . $config->version,
            'environment' => $config->environmentVariables,
            'ports' => [
                $config->port . ':6001',
                '${SOKETI_METRICS_PORT:-9601}:9601',
            ],
            'networks' => ['seaman'],
        ];

        return $this->addHealthCheckToConfig($composeConfig);
    }

    /**
     * @return list<int>
     */
    public function getRequiredPorts(): array
    {
        return [6001, 9601];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return new HealthCheck(
            test: ['CMD', 'wget', '--spider', '-q', 'http://localhost:6001'],
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
        $env = $config->environmentVariables;

        return [
            'SOKETI_PORT' => $config->port,
            'PUSHER_APP_ID' => $env['SOKETI_DEFAULT_APP_ID'] ?? 'app-id',
            'PUSHER_APP_KEY' => $env['SOKETI_DEFAULT_APP_KEY'] ?? 'app-key',
            'PUSHER_APP_SECRET' => $env['SOKETI_DEFAULT_APP_SECRET'] ?? 'app-secret',
            'PUSHER_HOST' => 'soketi',
            'PUSHER_PORT' => $config->port,
            'PUSHER_SCHEME' => 'http',
        ];
    }
}
