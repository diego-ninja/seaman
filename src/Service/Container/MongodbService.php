<?php

declare(strict_types=1);

// ABOUTME: MongoDB NoSQL database service implementation.
// ABOUTME: Configures MongoDB container for Seaman.

namespace Seaman\Service\Container;

use Seaman\Enum\Service;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

readonly class MongodbService extends AbstractService
{
    public function getType(): Service
    {
        return Service::MongoDB;
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
     * @param ServiceConfig $config
     * @return array<string, mixed>
     */
    public function generateComposeConfig(ServiceConfig $config): array
    {
        $healthCheck = $this->getHealthCheck();

        $composeConfig = [
            'image' => 'mongo:' . $config->version,
            'environment' => $config->environmentVariables,
            'ports' => [
                $config->port . ':27017',
            ],
            'volumes' => [
                'mongodb_data:/data/db',
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
}
