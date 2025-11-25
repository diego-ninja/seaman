<?php

declare(strict_types=1);

// ABOUTME: PostgreSQL database service implementation.
// ABOUTME: Configures PostgreSQL container for Seaman.

namespace Seaman\Service\Container;

use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

readonly class PostgresqlService implements ServiceInterface
{
    public function getName(): string
    {
        return 'postgresql';
    }

    public function getDisplayName(): string
    {
        return 'PostgreSQL';
    }

    public function getDescription(): string
    {
        return 'PostgreSQL relational database';
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
            name: 'postgresql',
            enabled: false,
            type: 'postgresql',
            version: '16',
            port: 5432,
            additionalPorts: [],
            environmentVariables: [
                'POSTGRES_DB' => 'seaman',
                'POSTGRES_USER' => 'seaman',
                'POSTGRES_PASSWORD' => 'seaman',
            ],
        );
    }

    /**
     * @param ServiceConfig $config
     * @return array<string, mixed>
     */
    public function generateComposeConfig(ServiceConfig $config): array
    {
        $healthCheck = $this->getHealthCheck();

        $composeConfig = [
            'image' => 'postgres:' . $config->version,
            'environment' => $config->environmentVariables,
            'ports' => [
                $config->port . ':5432',
            ],
            'volumes' => [
                'postgresql_data:/var/lib/postgresql/data',
            ],
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
        return [5432];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return new HealthCheck(
            test: ['CMD-SHELL', 'pg_isready -U $POSTGRES_USER'],
            interval: '10s',
            timeout: '5s',
            retries: 5,
        );
    }
}
