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

        $service['config'] = $newServiceConfig;
        $services[$serviceName] = $service;
        $existingConfig['services'] = $services;

        return $existingConfig;
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
