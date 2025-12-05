<?php

declare(strict_types=1);

// ABOUTME: Parses service configuration section from YAML data.
// ABOUTME: Handles individual service settings and collection creation.

namespace Seaman\Service\ConfigParser;

use RuntimeException;
use Seaman\Enum\Service;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;

final readonly class ServiceConfigParser
{
    /**
     * @param array<string, mixed> $data
     */
    public function parse(array $data): ServiceCollection
    {
        $servicesData = $data['services'] ?? [];
        if (!is_array($servicesData)) {
            throw new RuntimeException('Invalid services configuration');
        }

        /** @var array<string, ServiceConfig> $services */
        $services = [];
        foreach ($servicesData as $name => $serviceData) {
            if (!is_string($name) || !is_array($serviceData)) {
                continue;
            }

            /** @var array<string, mixed> $serviceData */
            $services[$name] = $this->parseServiceConfig($name, $serviceData);
        }

        return new ServiceCollection($services);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, ServiceConfig> $baseServices
     */
    public function merge(array $data, array $baseServices): ServiceCollection
    {
        $mergedServices = $baseServices;
        $servicesData = $data['services'] ?? [];

        if (!is_array($servicesData)) {
            return new ServiceCollection($mergedServices);
        }

        foreach ($servicesData as $name => $serviceData) {
            if (!is_string($name) || !is_array($serviceData)) {
                continue;
            }

            /** @var array<string, mixed> $serviceData */
            $mergedServices[$name] = $this->parseServiceConfig($name, $serviceData);
        }

        return new ServiceCollection($mergedServices);
    }

    /**
     * @param array<string, mixed> $serviceData
     */
    private function parseServiceConfig(string $name, array $serviceData): ServiceConfig
    {
        $enabled = $serviceData['enabled'] ?? false;
        if (!is_bool($enabled)) {
            $enabled = false;
        }

        $type = $serviceData['type'] ?? $name;
        if (!is_string($type)) {
            $type = $name;
        }

        $version = $serviceData['version'] ?? 'latest';
        if (!is_string($version)) {
            $version = 'latest';
        }

        $port = $serviceData['port'] ?? 0;
        if (!is_int($port)) {
            $port = 0;
        }

        $additionalPorts = $this->parseAdditionalPorts($serviceData);
        $envVars = $this->parseEnvironmentVariables($serviceData);

        return new ServiceConfig(
            name: $name,
            enabled: $enabled,
            type: Service::from($type),
            version: $version,
            port: $port,
            additionalPorts: $additionalPorts,
            environmentVariables: $envVars,
        );
    }

    /**
     * @param array<string, mixed> $serviceData
     * @return list<int>
     */
    private function parseAdditionalPorts(array $serviceData): array
    {
        $additionalPorts = $serviceData['additional_ports'] ?? [];
        if (!is_array($additionalPorts)) {
            return [];
        }

        /** @var list<int> $portsList */
        $portsList = [];
        foreach ($additionalPorts as $p) {
            if (is_int($p)) {
                $portsList[] = $p;
            }
        }

        return $portsList;
    }

    /**
     * @param array<string, mixed> $serviceData
     * @return array<string, string>
     */
    private function parseEnvironmentVariables(array $serviceData): array
    {
        $environmentVariables = $serviceData['environment'] ?? [];
        if (!is_array($environmentVariables)) {
            return [];
        }

        /** @var array<string, string> $envVars */
        $envVars = array_filter($environmentVariables, function ($value, $key) {
            return is_string($key) && is_string($value);
        }, ARRAY_FILTER_USE_BOTH);

        return $envVars;
    }
}
