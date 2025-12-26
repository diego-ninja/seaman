<?php

// ABOUTME: Adapts a plugin ServiceDefinition to ServiceInterface and optionally DatabaseServiceInterface.
// ABOUTME: Single adapter for all plugin services, delegating database operations when available.

declare(strict_types=1);

namespace Seaman\Plugin;

use Seaman\Contract\DatabaseServiceInterface;
use Seaman\Enum\Service;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Service\Container\ServiceInterface;
use Seaman\ValueObject\HealthCheck;
use Seaman\ValueObject\ServiceConfig;

final readonly class PluginServiceAdapter implements ServiceInterface, DatabaseServiceInterface
{
    public function __construct(
        private ServiceDefinition $definition,
    ) {}

    public function getType(): Service
    {
        // Try to match the service name to a known Service enum case
        foreach (Service::cases() as $case) {
            if ($case->value === $this->definition->name) {
                return $case;
            }
        }

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
            type: $this->getType(),
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
        $envVars = $config->environmentVariables;

        // Add port variable using service-specific naming
        $portVarName = strtoupper($this->definition->name) . '_PORT';

        // For database services, also add DB_PORT for compatibility
        if (in_array($this->definition->name, ['mysql', 'postgresql', 'mariadb', 'sqlite'], true)) {
            $envVars['DB_PORT'] = $config->port;
        }

        // Always add the service-specific port variable
        $envVars[$portVarName] = $config->port;

        return $envVars;
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

    public function getConfigSchema(): ?ConfigSchema
    {
        return $this->definition->configSchema;
    }

    public function supportsDatabaseOperations(): bool
    {
        return $this->definition->databaseOperations !== null;
    }

    /**
     * @return list<string>
     * @throws \LogicException When service does not support database operations
     */
    public function getDumpCommand(ServiceConfig $config): array
    {
        $operations = $this->definition->databaseOperations ?? throw new \LogicException(
            sprintf("Service '%s' does not support database operations", $this->definition->name),
        );

        return $operations->getDumpCommand($config);
    }

    /**
     * @return list<string>
     * @throws \LogicException When service does not support database operations
     */
    public function getRestoreCommand(ServiceConfig $config): array
    {
        $operations = $this->definition->databaseOperations ?? throw new \LogicException(
            sprintf("Service '%s' does not support database operations", $this->definition->name),
        );

        return $operations->getRestoreCommand($config);
    }

    /**
     * @return list<string>
     * @throws \LogicException When service does not support database operations
     */
    public function getShellCommand(ServiceConfig $config): array
    {
        $operations = $this->definition->databaseOperations ?? throw new \LogicException(
            sprintf("Service '%s' does not support database operations", $this->definition->name),
        );

        return $operations->getShellCommand($config);
    }
}
