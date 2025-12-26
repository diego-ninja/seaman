<?php

declare(strict_types=1);

// ABOUTME: Base class for Docker container service implementations.
// ABOUTME: Provides common functionality for all service types.

namespace Seaman\Service\Container;

use Seaman\Enum\Service;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\ValueObject\HealthCheck;
use Seaman\ValueObject\ServiceConfig;

abstract readonly class AbstractService implements ServiceInterface
{
    abstract public function getType(): Service;

    public function getName(): string
    {
        return $this->getType()->value;
    }

    public function getDisplayName(): string
    {
        return $this->getType()->name;
    }

    public function getDescription(): string
    {
        return $this->getType()->description();
    }

    public function getIcon(): string
    {
        return '⚙';
    }

    /**
     * @return list<string>
     */
    public function getDependencies(): array
    {
        return [];
    }

    /**
     * Formats a HealthCheck for Docker Compose configuration.
     *
     * @return array<string, mixed>|null
     */
    protected function formatHealthCheck(?HealthCheck $healthCheck): ?array
    {
        if ($healthCheck === null) {
            return null;
        }

        return [
            'test' => $healthCheck->test,
            'interval' => $healthCheck->interval,
            'timeout' => $healthCheck->timeout,
            'retries' => $healthCheck->retries,
        ];
    }

    /**
     * Adds healthcheck to compose config if present.
     *
     * @param array<string, mixed> $composeConfig
     * @return array<string, mixed>
     */
    protected function addHealthCheckToConfig(array $composeConfig): array
    {
        $healthCheck = $this->formatHealthCheck($this->getHealthCheck());
        if ($healthCheck !== null) {
            $composeConfig['healthcheck'] = $healthCheck;
        }

        return $composeConfig;
    }

    /**
     * @return list<int>
     */
    public function getInternalPorts(): array
    {
        return $this->getRequiredPorts();
    }

    public function getInspectInfo(ServiceConfig $config): string
    {
        return $config->version;
    }

    public function getConfigSchema(): ?ConfigSchema
    {
        return null;
    }
}
