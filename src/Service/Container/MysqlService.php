<?php

declare(strict_types=1);

// ABOUTME: MySQL database service implementation.
// ABOUTME: Configures MySQL container for Seaman.

namespace Seaman\Service\Container;

use Seaman\Contract\DatabaseServiceInterface;
use Seaman\Enum\Service;
use Seaman\ValueObject\HealthCheck;
use Seaman\ValueObject\ServiceConfig;

readonly class MysqlService extends AbstractService implements DatabaseServiceInterface
{
    public function getType(): Service
    {
        return Service::MySQL;
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: $this->getType()->value,
            enabled: false,
            type: $this->getType(),
            version: '8.0',
            port: $this->getType()->port(),
            additionalPorts: [],
            environmentVariables: [
                'MYSQL_DATABASE' => 'seaman',
                'MYSQL_USER' => 'seaman',
                'MYSQL_PASSWORD' => 'seaman',
                'MYSQL_ROOT_PASSWORD' => 'root',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function generateComposeConfig(ServiceConfig $config): array
    {
        $composeConfig = [
            'image' => 'mysql:' . $config->version,
            'environment' => $config->environmentVariables,
            'ports' => [$config->port . ':3306'],
            'volumes' => ['mysql_data:/var/lib/mysql'],
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
            test: ['CMD', 'mysqladmin', 'ping', '-h', 'localhost'],
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
            'DB_NAME' => $config->environmentVariables['MYSQL_DATABASE'] ?? 'seaman',
            'DB_USER' => $config->environmentVariables['MYSQL_USER'] ?? 'seaman',
            'DB_PASSWORD' => $config->environmentVariables['MYSQL_PASSWORD'] ?? 'seaman',
            'DB_ROOT_PASSWORD' => $config->environmentVariables['MYSQL_ROOT_PASSWORD'] ?? 'root',
        ];
    }

    /**
     * @return list<string>
     */
    public function getDumpCommand(ServiceConfig $config): array
    {
        $env = $config->environmentVariables;

        return [
            'mysqldump',
            '-u',
            $env['MYSQL_USER'] ?? 'root',
            '-p' . ($env['MYSQL_PASSWORD'] ?? ''),
            $env['MYSQL_DATABASE'] ?? 'mysql',
        ];
    }

    /**
     * @return list<string>
     */
    public function getRestoreCommand(ServiceConfig $config): array
    {
        $env = $config->environmentVariables;

        return [
            'mysql',
            '-u',
            $env['MYSQL_USER'] ?? 'root',
            '-p' . ($env['MYSQL_PASSWORD'] ?? ''),
            $env['MYSQL_DATABASE'] ?? 'mysql',
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
