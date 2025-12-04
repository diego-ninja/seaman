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
        $this->validateServices($config);
        $this->validateDatabase($config);
        $this->validateXdebug($config);
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
    private function validateDatabase(array $config): void
    {
        if (!isset($config['database'])) {
            return; // Optional section
        }

        if (!is_array($config['database'])) {
            throw new InvalidConfigurationException(
                'database must be an array',
                ['field' => 'database'],
            );
        }

        $db = $config['database'];

        // If database section exists, require essential fields
        $requiredFields = ['type', 'version'];
        foreach ($requiredFields as $field) {
            if (!isset($db[$field])) {
                throw new InvalidConfigurationException(
                    "Missing required database field: {$field}",
                    ['field' => "database.{$field}"],
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     * @throws InvalidConfigurationException
     */
    private function validateXdebug(array $config): void
    {
        if (!isset($config['xdebug'])) {
            return; // Optional section
        }

        if (!is_array($config['xdebug'])) {
            throw new InvalidConfigurationException(
                'xdebug must be an array',
                ['field' => 'xdebug'],
            );
        }
    }
}
