<?php

declare(strict_types=1);

// ABOUTME: SQLite file-based database service implementation.
// ABOUTME: Configures SQLite for local development without Docker container.

namespace Seaman\Service\Container;

use Seaman\Enum\Service;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

readonly class SqliteService extends AbstractService
{
    public function getType(): Service
    {
        return Service::SQLite;
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
            version: '3',
            port: 0,
            additionalPorts: [],
            environmentVariables: [
                'DATABASE_PATH' => 'var/data.db',
            ],
        );
    }

    /**
     * SQLite does not require a Docker container.
     *
     * @return array<string, mixed>
     */
    public function generateComposeConfig(ServiceConfig $config): array
    {
        return [];
    }

    /**
     * @return list<int>
     */
    public function getRequiredPorts(): array
    {
        return [];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return null;
    }

    /**
     * @return array<string, string|int>
     */
    public function getEnvVariables(ServiceConfig $config): array
    {
        $dbPath = $config->environmentVariables['DATABASE_PATH'] ?? 'var/data.db';

        return [
            'DATABASE_URL' => 'sqlite:///%kernel.project_dir%/' . $dbPath,
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $dbPath,
        ];
    }
}
