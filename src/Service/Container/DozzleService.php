<?php

declare(strict_types=1);

// ABOUTME: Dozzle realtime log viewer service.
// ABOUTME: Configures Dozzle for monitoring container logs.

namespace Seaman\Service\Container;

use Seaman\Enum\Service;
use Seaman\ValueObject\HealthCheck;
use Seaman\ValueObject\ServiceConfig;

readonly class DozzleService extends AbstractService
{
    public function getType(): Service
    {
        return Service::Dozzle;
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: $this->getType()->value,
            enabled: false,
            type: $this->getType(),
            version: 'latest',
            port: $this->getType()->port(),
            additionalPorts: [],
            environmentVariables: [
            ],
        );
    }

    public function generateComposeConfig(ServiceConfig $config): array
    {
        $healthCheck = $this->getHealthCheck();
        $composeConfig = [
            'image' => 'amir20/dozzle:' . $config->version,
            'ports' => ['${DOZZLE_PORT}:8080'],
            'volumes' => [
                '/var/run/docker.sock:/var/run/docker.sock',
            ],
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

    public function getRequiredPorts(): array
    {
        return [8080];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return new HealthCheck(
            test: ['CMD', '/dozzle', 'healthcheck'],
            interval: '3s',
            timeout: '30s',
            retries: 5,
        );
    }

    /**
     * @return array<string, string|int>
     */
    public function getEnvVariables(ServiceConfig $config): array
    {
        return [
            'DOZZLE_PORT' => $config->port,
        ];
    }
}
