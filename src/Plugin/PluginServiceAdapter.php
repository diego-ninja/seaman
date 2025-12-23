<?php

// ABOUTME: Adapts a plugin ServiceDefinition to ServiceInterface.
// ABOUTME: Bridges plugin services with the core service registry.

declare(strict_types=1);

namespace Seaman\Plugin;

use Seaman\Enum\Service;
use Seaman\Service\Container\ServiceInterface;
use Seaman\ValueObject\HealthCheck;
use Seaman\ValueObject\ServiceConfig;

final readonly class PluginServiceAdapter implements ServiceInterface
{
    public function __construct(
        private ServiceDefinition $definition,
    ) {}

    public function getType(): Service
    {
        return Service::Custom;
    }

    public function getName(): string
    {
        return $this->definition->name;
    }

    public function getDisplayName(): string
    {
        return $this->definition->getDisplayName();
    }

    public function getDescription(): string
    {
        return $this->definition->description;
    }

    public function getIcon(): string
    {
        return $this->definition->icon;
    }

    /**
     * @return list<string>
     */
    public function getDependencies(): array
    {
        return $this->definition->dependencies;
    }

    public function getDefaultConfig(): ServiceConfig
    {
        /** @var string $version */
        $version = $this->definition->defaultConfig['version'] ?? 'latest';
        /** @var array<string, string> $environment */
        $environment = $this->definition->defaultConfig['environment'] ?? [];

        $port = $this->definition->ports[0] ?? 0;
        $additionalPorts = array_slice($this->definition->ports, 1);

        return new ServiceConfig(
            name: $this->definition->name,
            enabled: true,
            type: Service::Custom,
            version: $version,
            port: $port,
            additionalPorts: $additionalPorts,
            environmentVariables: $environment,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function generateComposeConfig(ServiceConfig $config): array
    {
        return [
            '__template_path' => $this->definition->template,
        ];
    }

    /**
     * @return list<int>
     */
    public function getRequiredPorts(): array
    {
        return $this->definition->ports;
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return $this->definition->healthCheck;
    }

    /**
     * @return array<string, string|int>
     */
    public function getEnvVariables(ServiceConfig $config): array
    {
        return $config->environmentVariables;
    }

    /**
     * @return list<int>
     */
    public function getInternalPorts(): array
    {
        return $this->definition->internalPorts;
    }

    public function getInspectInfo(ServiceConfig $config): string
    {
        return "v{$config->version}";
    }
}
