<?php

// ABOUTME: Validates seaman configuration structure and values.
// ABOUTME: Throws InvalidConfigurationException for invalid configurations.

declare(strict_types=1);

namespace Seaman\Service;

use Seaman\Exception\InvalidConfigurationException;

final class ConfigurationValidator
{
    /**
     * @param array<string, mixed> $config
     * @throws InvalidConfigurationException
     */
    public function validate(array $config): void
    {
        $this->validateProjectName($config);
        $this->validateVersion($config);
        $this->validatePhp($config);
        $this->validateServices($config);
        $this->validateVolumes($config);
        $this->validateProjectType($config);
    }

    /**
     * @param array<string, mixed> $config
     * @throws InvalidConfigurationException
     */
    private function validateProjectName(array $config): void
    {
        if (!isset($config['project_name'])) {
            throw new InvalidConfigurationException(
                'Missing required field: project_name',
                ['field' => 'project_name'],
            );
        }

        if (!is_string($config['project_name']) || $config['project_name'] === '') {
            throw new InvalidConfigurationException(
                'project_name must be a non-empty string',
                ['field' => 'project_name', 'value' => $config['project_name']],
            );
        }

        // Validate project name format (alphanumeric, dash, underscore)
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $config['project_name'])) {
            throw new InvalidConfigurationException(
                'project_name can only contain letters, numbers, dashes, and underscores',
                ['field' => 'project_name', 'value' => $config['project_name']],
            );
        }
    }

    /**
     * @param array<string, mixed> $config
     * @throws InvalidConfigurationException
     */
    private function validateServices(array $config): void
    {
        if (!isset($config['services'])) {
            throw new InvalidConfigurationException(
                'Missing required field: services',
                ['field' => 'services'],
            );
        }

        if (!is_array($config['services'])) {
            throw new InvalidConfigurationException(
                'services must be an array',
                ['field' => 'services'],
            );
        }
    }

    /**
     * @param array<string, mixed> $config
     * @throws InvalidConfigurationException
     */
    private function validateVersion(array $config): void
    {
        // Version is optional, defaults to '1.0'
        if (!isset($config['version'])) {
            return;
        }

        if (!is_string($config['version'])) {
            throw new InvalidConfigurationException(
                'version must be a string',
                ['field' => 'version'],
            );
        }
    }

    /**
     * @param array<string, mixed> $config
     * @throws InvalidConfigurationException
     */
    private function validatePhp(array $config): void
    {
        if (!isset($config['php'])) {
            throw new InvalidConfigurationException(
                'Missing required field: php',
                ['field' => 'php'],
            );
        }

        if (!is_array($config['php'])) {
            throw new InvalidConfigurationException(
                'php must be an array',
                ['field' => 'php'],
            );
        }

        $php = $config['php'];

        // version is required
        if (!isset($php['version'])) {
            throw new InvalidConfigurationException(
                'Missing required php field: version',
                ['field' => 'php.version'],
            );
        }

        if (!is_string($php['version'])) {
            throw new InvalidConfigurationException(
                'php.version must be a string',
                ['field' => 'php.version'],
            );
        }

        // xdebug is optional
        if (isset($php['xdebug'])) {
            if (!is_array($php['xdebug'])) {
                throw new InvalidConfigurationException(
                    'php.xdebug must be an array',
                    ['field' => 'php.xdebug'],
                );
            }

            $xdebug = $php['xdebug'];

            // If xdebug exists, validate its fields
            if (isset($xdebug['enabled']) && !is_bool($xdebug['enabled'])) {
                throw new InvalidConfigurationException(
                    'php.xdebug.enabled must be a boolean',
                    ['field' => 'php.xdebug.enabled'],
                );
            }

            if (isset($xdebug['ide_key']) && !is_string($xdebug['ide_key'])) {
                throw new InvalidConfigurationException(
                    'php.xdebug.ide_key must be a string',
                    ['field' => 'php.xdebug.ide_key'],
                );
            }

            if (isset($xdebug['client_host']) && !is_string($xdebug['client_host'])) {
                throw new InvalidConfigurationException(
                    'php.xdebug.client_host must be a string',
                    ['field' => 'php.xdebug.client_host'],
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     * @throws InvalidConfigurationException
     */
    private function validateVolumes(array $config): void
    {
        // Volumes is optional
        if (!isset($config['volumes'])) {
            return;
        }

        if (!is_array($config['volumes'])) {
            throw new InvalidConfigurationException(
                'volumes must be an array',
                ['field' => 'volumes'],
            );
        }

        $volumes = $config['volumes'];

        // persist is optional
        if (isset($volumes['persist']) && !is_array($volumes['persist'])) {
            throw new InvalidConfigurationException(
                'volumes.persist must be an array',
                ['field' => 'volumes.persist'],
            );
        }
    }

    /**
     * @param array<string, mixed> $config
     * @throws InvalidConfigurationException
     */
    private function validateProjectType(array $config): void
    {
        // project_type is optional
        if (!isset($config['project_type'])) {
            return;
        }

        if (!is_string($config['project_type'])) {
            throw new InvalidConfigurationException(
                'project_type must be a string',
                ['field' => 'project_type'],
            );
        }
    }
}
