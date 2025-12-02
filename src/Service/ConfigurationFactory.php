<?php

declare(strict_types=1);

// ABOUTME: Creates Configuration objects from user choices.
// ABOUTME: Handles service configuration building and volume persistence logic.

namespace Seaman\Service;

use Seaman\Enum\ProjectType;
use Seaman\Enum\Service;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\InitializationChoices;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\VolumeConfig;

class ConfigurationFactory
{
    public function __construct(
        private readonly ServiceRegistry $registry,
    ) {}

    public function createFromChoices(
        InitializationChoices $choices,
        ProjectType $projectType,
    ): Configuration {
        $php = new PhpConfig($choices->phpVersion, $choices->xdebug);

        $serviceConfigs = $this->buildServiceConfigs($choices->database, $choices->services);
        $persistVolumes = $this->determinePersistVolumes($choices->database, $choices->services);

        return new Configuration(
            version: '1.0',
            php: $php,
            services: new ServiceCollection($serviceConfigs),
            volumes: new VolumeConfig($persistVolumes),
            projectType: $projectType,
        );
    }

    /**
     * @param list<Service> $services
     * @return array<string, ServiceConfig>
     */
    private function buildServiceConfigs(Service $database, array $services): array
    {
        /** @var array<string, ServiceConfig> $serviceConfigs */
        $serviceConfigs = [];

        // Add database if selected
        if ($database !== Service::None) {
            $serviceImpl = $this->registry->get($database);
            $defaultConfig = $serviceImpl->getDefaultConfig();
            $serviceConfigs[$database->value] = new ServiceConfig(
                name: $defaultConfig->name,
                enabled: true,
                type: $defaultConfig->type,
                version: $defaultConfig->version,
                port: $defaultConfig->port,
                additionalPorts: $defaultConfig->additionalPorts,
                environmentVariables: $defaultConfig->environmentVariables,
            );
        }

        // Add additional services
        foreach ($services as $serviceName) {
            $serviceImpl = $this->registry->get($serviceName);
            $defaultConfig = $serviceImpl->getDefaultConfig();
            $serviceConfigs[$serviceName->value] = new ServiceConfig(
                name: $defaultConfig->name,
                enabled: true,
                type: $defaultConfig->type,
                version: $defaultConfig->version,
                port: $defaultConfig->port,
                additionalPorts: $defaultConfig->additionalPorts,
                environmentVariables: $defaultConfig->environmentVariables,
            );
        }

        return $serviceConfigs;
    }

    /**
     * Determine which services should have persisted volumes.
     *
     * @param list<Service> $services
     * @return list<string>
     */
    private function determinePersistVolumes(Service $database, array $services): array
    {
        /** @var list<string> $persistVolumes */
        $persistVolumes = [];

        // Add database to persist volumes
        if ($database !== Service::None) {
            $persistVolumes[] = $database->value;
        }

        // Services that need data persistence
        $persistableServices = [
            Service::Redis,
            Service::Memcached,
            Service::MinIO,
            Service::Elasticsearch,
            Service::RabbitMq,
            Service::MongoDB,
        ];

        foreach ($services as $serviceName) {
            if (in_array($serviceName, $persistableServices, true)) {
                $persistVolumes[] = $serviceName->value;
            }
        }

        return $persistVolumes;
    }
}
