<?php

declare(strict_types=1);

// ABOUTME: Interface for pluggable Docker services.
// ABOUTME: Each service defines its config, dependencies, and compose generation.

namespace Seaman\Service\Container;

use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

interface ServiceInterface
{
    public function getName(): string;

    public function getDisplayName(): string;

    public function getDescription(): string;

    /**
     * @return list<string> Service names this service depends on
     */
    public function getDependencies(): array;

    public function getDefaultConfig(): ServiceConfig;

    /**
     * @param ServiceConfig $config
     * @return array<string, mixed>
     */
    public function generateComposeConfig(ServiceConfig $config): array;

    /**
     * @return list<int>
     */
    public function getRequiredPorts(): array;

    public function getHealthCheck(): ?HealthCheck;
}
