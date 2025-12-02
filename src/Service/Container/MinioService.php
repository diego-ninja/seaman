<?php

declare(strict_types=1);

// ABOUTME: MinIO S3-compatible storage service.
// ABOUTME: Configures MinIO for local object storage.

namespace Seaman\Service\Container;

use Seaman\Enum\Service;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

readonly class MinioService extends AbstractService
{
    public function getType(): Service
    {
        return Service::MinIO;
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
            name: $this->getType()->value,
            enabled: false,
            type: $this->getType(),
            version: 'latest',
            port: $this->getType()->port(),
            additionalPorts: [9001],
            environmentVariables: [
                'MINIO_ROOT_USER' => 'minioadmin',
                'MINIO_ROOT_PASSWORD' => 'minioadmin',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function generateComposeConfig(ServiceConfig $config): array
    {
        $healthCheck = $this->getHealthCheck();
        $composeConfig = [
            'image' => 'minio/minio:' . $config->version,
            'command' => 'server /data --console-address ":9001"',
            'environment' => [
                'MINIO_ROOT_USER=${MINIO_ROOT_USER:-minioadmin}',
                'MINIO_ROOT_PASSWORD=${MINIO_ROOT_PASSWORD:-minioadmin}',
            ],
            'ports' => [
                '${MINIO_PORT}:9000',
                '${MINIO_CONSOLE_PORT}:9001',
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

    /**
     * @return list<int>
     */
    public function getRequiredPorts(): array
    {
        return [9000, 9001];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return new HealthCheck(
            test: ['CMD', 'curl', '-f', 'http://localhost:9000/minio/health/live'],
            interval: '30s',
            timeout: '20s',
            retries: 3,
        );
    }

    /**
     * @return array<string, string|int>
     */
    public function getEnvVariables(ServiceConfig $config): array
    {
        return [
            'MINIO_PORT' => $config->port,
            'MINIO_CONSOLE_PORT' => $config->additionalPorts[0] ?? 9001,
            'MINIO_ROOT_USER' => $config->environmentVariables['MINIO_ROOT_USER'] ?? 'minioadmin',
            'MINIO_ROOT_PASSWORD' => $config->environmentVariables['MINIO_ROOT_PASSWORD'] ?? 'minioadmin',
        ];
    }
}
