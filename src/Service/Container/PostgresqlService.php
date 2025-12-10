<?php

declare(strict_types=1);

// ABOUTME: PostgreSQL database service implementation.
// ABOUTME: Configures PostgreSQL container for Seaman.

namespace Seaman\Service\Container;

use Seaman\Contract\DatabaseServiceInterface;
use Seaman\Enum\Service;
use Seaman\ValueObject\HealthCheck;
use Seaman\ValueObject\ServiceConfig;

readonly class PostgresqlService extends AbstractService implements DatabaseServiceInterface
{
    public function getType(): Service
    {
        return Service::PostgreSQL;
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: $this->getType()->value,
            enabled: false,
            type: $this->getType(),
            version: '16',
            port: $this->getType()->port(),
            additionalPorts: [],
            environmentVariables: [
                'POSTGRES_DB' => 'seaman',
                'POSTGRES_USER' => 'seaman',
                'POSTGRES_PASSWORD' => 'seaman',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function generateComposeConfig(ServiceConfig $config): array
    {
        $composeConfig = [
            'image' => 'postgres:' . $config->version,
            'environment' => $config->environmentVariables,
            'ports' => [$config->port . ':5432'],
            'volumes' => ['postgresql_data:/var/lib/postgresql/data'],
        ];

        return $this->addHealthCheckToConfig($composeConfig);
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

    /**
     * @return array<string, string|int>
     */
    public function getEnvVariables(ServiceConfig $config): array
    {
        return [
            'DB_PORT' => $config->port,
            'DB_NAME' => $config->environmentVariables['POSTGRES_DB'] ?? 'seaman',
            'DB_USER' => $config->environmentVariables['POSTGRES_USER'] ?? 'seaman',
            'DB_PASSWORD' => $config->environmentVariables['POSTGRES_PASSWORD'] ?? 'seaman',
        ];
    }

    /**
     * @return list<string>
     */
    public function getDumpCommand(ServiceConfig $config): array
    {
        $env = $config->environmentVariables;

        return [
            'pg_dump',
            '-U',
            $env['POSTGRES_USER'] ?? 'postgres',
            $env['POSTGRES_DB'] ?? 'postgres',
        ];
    }

    /**
     * @return list<string>
     */
    public function getRestoreCommand(ServiceConfig $config): array
    {
        $env = $config->environmentVariables;

        return [
            'psql',
            '-U',
            $env['POSTGRES_USER'] ?? 'postgres',
            $env['POSTGRES_DB'] ?? 'postgres',
        ];
    }

    /**
     * @return list<string>
     */
    public function getShellCommand(ServiceConfig $config): array
    {
        return $this->getRestoreCommand($config);
    }

    public function getInspectInfo(ServiceConfig $config): string
    {
        $env = $config->environmentVariables;

        return sprintf(
            'v%s | %s:%s',
            $config->version,
            $env['POSTGRES_USER'] ?? 'seaman',
            $env['POSTGRES_PASSWORD'] ?? 'seaman',
        );
    }
}
