<?php

// ABOUTME: Orchestrates interactive service configuration.
// ABOUTME: Loads, validates, and saves service config to seaman.yaml.

declare(strict_types=1);

namespace Seaman\Service;

use Seaman\Plugin\Config\BooleanField;
use Seaman\Plugin\Config\FieldInterface;
use Seaman\Plugin\Config\IntegerField;
use Seaman\Plugin\Config\StringField;

final readonly class ConfigurationService
{
    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function extractServiceConfig(string $serviceName, array $config): array
    {
        if (!isset($config['services'])) {
            return [];
        }

        /** @var mixed $services */
        $services = $config['services'];

        if (!is_array($services) || !isset($services[$serviceName])) {
            return [];
        }

        /** @var mixed $service */
        $service = $services[$serviceName];

        if (!is_array($service) || !isset($service['config'])) {
            return [];
        }

        /** @var mixed $serviceConfig */
        $serviceConfig = $service['config'];

        if (!is_array($serviceConfig)) {
            return [];
        }

        /** @var array<string, mixed> */
        return $serviceConfig;
    }

    /**
     * @param array<string, mixed> $existingConfig
     * @param array<string, mixed> $newServiceConfig
     * @return array<string, mixed>
     */
    public function mergeConfig(
        array $existingConfig,
        string $serviceName,
        array $newServiceConfig,
    ): array {
        if (!isset($existingConfig['services'])) {
            $existingConfig['services'] = [];
        }

        /** @var mixed $services */
        $services = $existingConfig['services'];

        if (!is_array($services)) {
            $services = [];
        }

        if (!isset($services[$serviceName])) {
            $services[$serviceName] = [];
        }

        /** @var mixed $service */
        $service = $services[$serviceName];

        if (!is_array($service)) {
            $service = [];
        }

        $normalizedService = [];
        foreach ($service as $key => $value) {
            if (is_string($key)) {
                $normalizedService[$key] = $value;
            }
        }

        $normalizedService['config'] = $newServiceConfig;
        $normalizedService = $this->materializeRuntimeConfig($serviceName, $normalizedService, $newServiceConfig);
        $services[$serviceName] = $normalizedService;
        $existingConfig['services'] = $services;

        return $existingConfig;
    }

    /**
     * @param array<string, mixed> $service
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function materializeRuntimeConfig(string $serviceName, array $service, array $config): array
    {
        if (isset($config['version']) && is_string($config['version'])) {
            $service['version'] = $config['version'];
        }

        if (isset($config['port']) && is_int($config['port'])) {
            $service['port'] = $config['port'];
        }

        $additionalPorts = [];
        foreach ($config as $key => $value) {
            if ($key !== 'port' && str_ends_with($key, '_port') && is_int($value)) {
                $additionalPorts[] = $value;
            }
        }
        if ($additionalPorts !== []) {
            $service['additional_ports'] = $additionalPorts;
        }

        $environment = $this->existingEnvironment($service);
        foreach ($config as $key => $value) {
            if ($key === 'version' || !is_string($value) && !is_int($value) && !is_bool($value)) {
                continue;
            }

            $environmentValue = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
            foreach ($this->environmentVariableNames($serviceName, $key) as $variable) {
                $environment[$variable] = $environmentValue;
            }
        }

        if ($environment !== []) {
            $service['environment'] = $environment;
        }

        return $service;
    }

    /**
     * @param array<string, mixed> $service
     * @return array<string, string>
     */
    private function existingEnvironment(array $service): array
    {
        $rawEnvironment = $service['environment'] ?? [];
        if (!is_array($rawEnvironment)) {
            return [];
        }

        $environment = [];
        foreach ($rawEnvironment as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $environment[$key] = $value;
            }
        }

        return $environment;
    }

    /**
     * @return list<string>
     */
    private function environmentVariableNames(string $serviceName, string $configKey): array
    {
        $mapping = match ($serviceName . ':' . $configKey) {
            'mysql:port', 'mariadb:port', 'postgresql:port' => ['DB_PORT'],
            'mysql:database' => ['MYSQL_DATABASE', 'DB_NAME'],
            'mysql:user' => ['MYSQL_USER', 'DB_USER'],
            'mysql:password' => ['MYSQL_PASSWORD', 'DB_PASSWORD'],
            'mysql:root_password' => ['MYSQL_ROOT_PASSWORD', 'DB_ROOT_PASSWORD'],
            'mariadb:database' => ['MARIADB_DATABASE', 'DB_NAME'],
            'mariadb:user' => ['MARIADB_USER', 'DB_USER'],
            'mariadb:password' => ['MARIADB_PASSWORD', 'DB_PASSWORD'],
            'mariadb:root_password' => ['MARIADB_ROOT_PASSWORD', 'DB_ROOT_PASSWORD'],
            'postgresql:database' => ['POSTGRES_DB', 'DB_NAME'],
            'postgresql:user' => ['POSTGRES_USER', 'DB_USER'],
            'postgresql:password' => ['POSTGRES_PASSWORD', 'DB_PASSWORD'],
            'mongodb:port' => ['MONGO_PORT'],
            'mongodb:database' => ['MONGO_INITDB_DATABASE', 'MONGO_DB'],
            'mongodb:user' => ['MONGO_INITDB_ROOT_USERNAME', 'MONGO_USER'],
            'mongodb:password' => ['MONGO_INITDB_ROOT_PASSWORD', 'MONGO_PASSWORD'],
            'soketi:app_id' => ['PUSHER_APP_ID'],
            'soketi:app_key' => ['PUSHER_APP_KEY'],
            'soketi:app_secret' => ['PUSHER_APP_SECRET'],
            'sqlite:database_path' => ['DATABASE_PATH'],
            default => [strtoupper(str_replace('-', '_', $serviceName . '_' . $configKey))],
        };

        return $mapping;
    }

    /**
     * @param array<string, mixed> $currentConfig
     * @return array<string, mixed>
     */
    public function buildPromptConfig(FieldInterface $field, array $currentConfig): array
    {
        $metadata = $field->getMetadata();
        $name = $field->getName();
        $default = $currentConfig[$name] ?? $field->getDefault();

        if ($field instanceof BooleanField) {
            return [
                'type' => 'confirm',
                'label' => $metadata->label,
                'hint' => $metadata->description,
                'default' => (bool) $default,
            ];
        }

        if ($field instanceof IntegerField) {
            $defaultValue = $default ?? 0;
            if (!is_int($defaultValue)) {
                $defaultValue = 0;
            }

            return [
                'type' => 'text',
                'label' => $metadata->label,
                'hint' => $metadata->description,
                'default' => (string) $defaultValue,
            ];
        }

        if ($field instanceof StringField) {
            if ($metadata->isSecret) {
                return [
                    'type' => 'password',
                    'label' => $metadata->label,
                    'hint' => $metadata->description,
                ];
            }

            $enum = $field->getEnum();
            if ($enum !== null) {
                $defaultValue = $default ?? '';
                if (!is_string($defaultValue)) {
                    $defaultValue = '';
                }

                return [
                    'type' => 'select',
                    'label' => $metadata->label,
                    'hint' => $metadata->description,
                    'options' => $enum,
                    'default' => $defaultValue,
                ];
            }

            $defaultValue = $default ?? '';
            if (!is_string($defaultValue)) {
                $defaultValue = '';
            }

            return [
                'type' => 'text',
                'label' => $metadata->label,
                'hint' => $metadata->description,
                'default' => $defaultValue,
            ];
        }

        throw new \InvalidArgumentException("Unknown field type: " . $field::class);
    }
}
