<?php

declare(strict_types=1);

// ABOUTME: MySQL database service implementation.
// ABOUTME: Configures MySQL container for Seaman.

namespace Seaman\Service\Container;

use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

readonly class MysqlService implements ServiceInterface
{
    public function getName(): string
    {
        return 'mysql';
    }

    public function getDisplayName(): string
    {
        return 'MySQL';
    }

    public function getDescription(): string
    {
        return 'MySQL relational database';
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
            name: 'mysql',
            enabled: false,
            type: 'mysql',
            version: '8.0',
            port: 3306,
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
     * @param ServiceConfig $config
     * @return array<string, mixed>
     */
    public function generateComposeConfig(ServiceConfig $config): array
    {
        $healthCheck = $this->getHealthCheck();

        $composeConfig = [
            'image' => 'mysql:' . $config->version,
            'environment' => $config->environmentVariables,
            'ports' => [
                $config->port . ':3306',
            ],
            'volumes' => [
                'mysql_data:/var/lib/mysql',
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
}
