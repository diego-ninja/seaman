<?php

declare(strict_types=1);

// ABOUTME: MongoDB NoSQL database service implementation.
// ABOUTME: Configures MongoDB container for Seaman.

namespace Seaman\Service\Container;

use Seaman\Contract\DatabaseServiceInterface;
use Seaman\Enum\Service;
use Seaman\ValueObject\HealthCheck;
use Seaman\ValueObject\ServiceConfig;

readonly class MongodbService extends AbstractService implements DatabaseServiceInterface
{
    public function getType(): Service
    {
        return Service::MongoDB;
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: $this->getType()->value,
            enabled: false,
            type: $this->getType(),
            version: '7',
            port: $this->getType()->port(),
            additionalPorts: [],
            environmentVariables: [
                'MONGO_INITDB_ROOT_USERNAME' => 'seaman',
                'MONGO_INITDB_ROOT_PASSWORD' => 'seaman',
                'MONGO_INITDB_DATABASE' => 'seaman',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function generateComposeConfig(ServiceConfig $config): array
    {
        $composeConfig = [
            'image' => 'mongo:' . $config->version,
            'environment' => $config->environmentVariables,
            'ports' => [$config->port . ':27017'],
            'volumes' => ['mongodb_data:/data/db'],
        ];

        return $this->addHealthCheckToConfig($composeConfig);
    }

    /**
     * @return list<int>
     */
    public function getRequiredPorts(): array
    {
        return [27017];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return new HealthCheck(
            test: ['CMD', 'mongosh', '--eval', 'db.adminCommand("ping")'],
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
            'MONGO_PORT' => $config->port,
            'MONGO_USER' => $config->environmentVariables['MONGO_INITDB_ROOT_USERNAME'] ?? 'seaman',
            'MONGO_PASSWORD' => $config->environmentVariables['MONGO_INITDB_ROOT_PASSWORD'] ?? 'seaman',
            'MONGO_DB' => $config->environmentVariables['MONGO_INITDB_DATABASE'] ?? 'seaman',
        ];
    }

    /**
     * @return list<string>
     */
    public function getDumpCommand(ServiceConfig $config): array
    {
        $env = $config->environmentVariables;

        return [
            'mongodump',
            '--username',
            $env['MONGO_INITDB_ROOT_USERNAME'] ?? 'root',
            '--password',
            $env['MONGO_INITDB_ROOT_PASSWORD'] ?? '',
            '--authenticationDatabase',
            'admin',
            '--archive',
        ];
    }

    /**
     * @return list<string>
     */
    public function getRestoreCommand(ServiceConfig $config): array
    {
        $env = $config->environmentVariables;

        return [
            'mongorestore',
            '--username',
            $env['MONGO_INITDB_ROOT_USERNAME'] ?? 'root',
            '--password',
            $env['MONGO_INITDB_ROOT_PASSWORD'] ?? '',
            '--authenticationDatabase',
            'admin',
            '--archive',
            '--drop',
        ];
    }

    /**
     * @return list<string>
     */
    public function getShellCommand(ServiceConfig $config): array
    {
        $env = $config->environmentVariables;

        return [
            'mongosh',
            '--username',
            $env['MONGO_INITDB_ROOT_USERNAME'] ?? 'root',
            '--password',
            $env['MONGO_INITDB_ROOT_PASSWORD'] ?? '',
            '--authenticationDatabase',
            'admin',
        ];
    }
}
