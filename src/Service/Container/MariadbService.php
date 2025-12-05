<?php

declare(strict_types=1);

// ABOUTME: MariaDB database service implementation.
// ABOUTME: Configures MariaDB container for Seaman.

namespace Seaman\Service\Container;

use Seaman\Contract\DatabaseServiceInterface;
use Seaman\Enum\Service;
use Seaman\ValueObject\HealthCheck;
use Seaman\ValueObject\ServiceConfig;

readonly class MariadbService extends AbstractService implements DatabaseServiceInterface
{
    public function getType(): Service
    {
        return Service::MariaDB;
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: $this->getType()->value,
            enabled: false,
            type: $this->getType(),
            version: '11',
            port: $this->getType()->port(),
            additionalPorts: [],
            environmentVariables: [
                'MARIADB_DATABASE' => 'seaman',
                'MARIADB_USER' => 'seaman',
                'MARIADB_PASSWORD' => 'seaman',
                'MARIADB_ROOT_PASSWORD' => 'root',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function generateComposeConfig(ServiceConfig $config): array
    {
        $composeConfig = [
            'image' => 'mariadb:' . $config->version,
            'environment' => $config->environmentVariables,
            'ports' => [$config->port . ':3306'],
            'volumes' => ['mariadb_data:/var/lib/mysql'],
        ];

        return $this->addHealthCheckToConfig($composeConfig);
    }

    /**
     * @return list<int>
     */
    public function getRequiredPorts(): array
    {
        return [3306];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return new HealthCheck(
            test: ['CMD-SHELL', 'healthcheck.sh --connect --innodb_initialized'],
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
            'DB_NAME' => $config->environmentVariables['MARIADB_DATABASE'] ?? 'seaman',
            'DB_USER' => $config->environmentVariables['MARIADB_USER'] ?? 'seaman',
            'DB_PASSWORD' => $config->environmentVariables['MARIADB_PASSWORD'] ?? 'seaman',
            'DB_ROOT_PASSWORD' => $config->environmentVariables['MARIADB_ROOT_PASSWORD'] ?? 'root',
        ];
    }

    /**
     * @return list<string>
     */
    public function getDumpCommand(ServiceConfig $config): array
    {
        $env = $config->environmentVariables;

        return [
            'mariadb-dump',
            '-u',
            $env['MARIADB_USER'] ?? 'root',
            '-p' . ($env['MARIADB_PASSWORD'] ?? ''),
            $env['MARIADB_DATABASE'] ?? 'mysql',
        ];
    }

    /**
     * @return list<string>
     */
    public function getRestoreCommand(ServiceConfig $config): array
    {
        $env = $config->environmentVariables;

        return [
            'mariadb',
            '-u',
            $env['MARIADB_USER'] ?? 'root',
            '-p' . ($env['MARIADB_PASSWORD'] ?? ''),
            $env['MARIADB_DATABASE'] ?? 'mysql',
        ];
    }

    /**
     * @return list<string>
     */
    public function getShellCommand(ServiceConfig $config): array
    {
        return $this->getRestoreCommand($config);
    }
}
