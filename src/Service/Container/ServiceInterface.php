<?php

declare(strict_types=1);

// ABOUTME: Interface for Docker container service implementations.
// ABOUTME: Defines contract for service configuration and metadata.

namespace Seaman\Service\Container;

use Seaman\Enum\Service;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

interface ServiceInterface
{
    public function getType(): Service;
    public function getName(): string;

    public function getDisplayName(): string;

    public function getDescription(): string;

    public function getIcon(): string;

    /**
     * @return list<string>
     */
    public function getDependencies(): array;

    public function getDefaultConfig(): ServiceConfig;

    /**
     * @return array<string, mixed> Docker Compose service definition
     */
    public function generateComposeConfig(ServiceConfig $config): array;

    /**
     * @return list<int> Ports this service requires
     */
    public function getRequiredPorts(): array;

    public function getHealthCheck(): ?HealthCheck;

    /**
     * @return array<string, string|int> Environment variables to generate in .env file
     */
    public function getEnvVariables(ServiceConfig $config): array;

    /**
     * @return list<int> Internal Docker ports for container-to-container communication
     */
    public function getInternalPorts(): array;

    /**
     * Get display info for inspect command (credentials, version, etc.).
     */
    public function getInspectInfo(ServiceConfig $config): string;
}
