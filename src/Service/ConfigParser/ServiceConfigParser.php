<?php

declare(strict_types=1);

// ABOUTME: Parses service configuration section from YAML data.
// ABOUTME: Handles individual service settings and collection creation.

namespace Seaman\Service\ConfigParser;

use Seaman\Enum\Service;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;

final readonly class ServiceConfigParser
{
    use ConfigDataExtractor;

    /**
     * @param array<string, mixed> $data
     */
    public function parse(array $data): ServiceCollection
    {
        $servicesData = $this->requireArray($data, 'services', 'Invalid services configuration: expected array');

        /** @var array<string, ServiceConfig> $services */
        $services = [];
        foreach ($servicesData as $name => $serviceData) {
            // YAML can produce integer keys for numeric-looking strings
            /** @phpstan-ignore function.alreadyNarrowedType */
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
        $servicesData = $this->getArray($data, 'services');

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
        return new ServiceConfig(
            name: $name,
            enabled: $this->getBool($serviceData, 'enabled', false),
            type: Service::from($this->getString($serviceData, 'type', $name)),
            version: $this->getString($serviceData, 'version', 'latest'),
            port: $this->getInt($serviceData, 'port', 0),
            additionalPorts: $this->parseAdditionalPorts($serviceData),
            environmentVariables: $this->parseEnvironmentVariables($serviceData),
        );
    }

    /**
     * @param array<string, mixed> $serviceData
     * @return list<int>
     */
    private function parseAdditionalPorts(array $serviceData): array
    {
        $additionalPorts = $this->getArray($serviceData, 'additional_ports');

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
        $environmentVariables = $this->getArray($serviceData, 'environment');

        /** @var array<string, string> $envVars */
        $envVars = array_filter($environmentVariables, function ($value, $key) {
            return is_string($key) && is_string($value);
        }, ARRAY_FILTER_USE_BOTH);

        return $envVars;
    }
}
