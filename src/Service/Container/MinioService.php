<?php

declare(strict_types=1);

// ABOUTME: MinIO S3-compatible storage service.
// ABOUTME: Configures MinIO for local object storage.

namespace Seaman\Service\Container;

use Seaman\Enum\Service;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

readonly class MinioService implements ServiceInterface
{
    public function getName(): string
    {
        return Service::MinIO->value;
    }

    public function getDisplayName(): string
    {
        return Service::MinIO->name;
    }

    public function getDescription(): string
    {
        return 'S3-compatible object storage';
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
            name: Service::MinIO->value,
            enabled: false,
            type: 'minio',
            version: 'latest',
            port: 9000,
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
            'image' => 'minio/minio:latest',
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
}
