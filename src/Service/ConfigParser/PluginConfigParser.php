<?php

declare(strict_types=1);

// ABOUTME: Parses plugin configuration section from YAML data.
// ABOUTME: Handles plugin settings extraction and validation.

namespace Seaman\Service\ConfigParser;

final readonly class PluginConfigParser
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, array<string, mixed>>
     */
    public function parse(array $data): array
    {
        $pluginsData = $data['plugins'] ?? [];
        if (!is_array($pluginsData)) {
            return [];
        }

        return $this->normalizePluginConfig($pluginsData);
    }

    /**
     * @param array<int|string, mixed> $pluginsData
     * @return array<string, array<string, mixed>>
     */
    private function normalizePluginConfig(array $pluginsData): array
    {
        /** @var array<string, array<string, mixed>> $normalized */
        $normalized = [];

        foreach ($pluginsData as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (!is_array($value)) {
                continue;
            }

            /** @var array<string, mixed> $value */
            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
