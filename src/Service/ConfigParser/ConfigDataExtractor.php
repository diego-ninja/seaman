<?php

// ABOUTME: Provides type-safe data extraction methods for config parsers.
// ABOUTME: Reduces boilerplate code for parsing YAML configuration values.

declare(strict_types=1);

namespace Seaman\Service\ConfigParser;

trait ConfigDataExtractor
{
    /**
     * Extract a string value with default.
     *
     * @param array<string, mixed> $data
     */
    protected function getString(array $data, string $key, string $default): string
    {
        $value = $data[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    /**
     * Extract a boolean value with default.
     *
     * @param array<string, mixed> $data
     */
    protected function getBool(array $data, string $key, bool $default): bool
    {
        $value = $data[$key] ?? $default;

        return is_bool($value) ? $value : $default;
    }

    /**
     * Extract an integer value with default.
     *
     * @param array<string, mixed> $data
     */
    protected function getInt(array $data, string $key, int $default): int
    {
        $value = $data[$key] ?? $default;

        return is_int($value) ? $value : $default;
    }

    /**
     * Extract an array value with default.
     *
     * @param array<string, mixed> $data
     * @param array<mixed> $default
     * @return array<mixed>
     */
    protected function getArray(array $data, string $key, array $default = []): array
    {
        $value = $data[$key] ?? $default;

        return is_array($value) ? $value : $default;
    }

    /**
     * Extract a subsection array, throwing on invalid type.
     *
     * @param array<string, mixed> $data
     * @param string $message Error message if not an array
     * @return array<string, mixed>
     * @throws \Seaman\Exception\InvalidConfigurationException
     */
    protected function requireArray(array $data, string $key, string $message): array
    {
        $value = $data[$key] ?? [];

        if (!is_array($value)) {
            throw new \Seaman\Exception\InvalidConfigurationException($message);
        }

        /** @var array<string, mixed> $value */
        return $value;
    }
}
