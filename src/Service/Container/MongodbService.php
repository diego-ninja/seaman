<?php

declare(strict_types=1);

// ABOUTME: MongoDB NoSQL database service implementation.
// ABOUTME: Configures MongoDB container for Seaman.

namespace Seaman\Service\Container;

use Seaman\Enum\Service;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

readonly class MongodbService implements ServiceInterface
{
    public function getName(): string
    {
        return Service::MongoDB->value;
    }

    public function getDisplayName(): string
    {
        return Service::MongoDB->name;
    }

    public function getDescription(): string
    {
        return 'MongoDB NoSQL database';
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
            name: Service::MongoDB->value,
            enabled: false,
            type: 'mongodb',
            version: '7',
            port: 27017,
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
}
