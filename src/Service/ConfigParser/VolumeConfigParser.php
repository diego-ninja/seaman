<?php

declare(strict_types=1);

// ABOUTME: Parses volume configuration section from YAML data.
// ABOUTME: Handles persistent volume settings parsing.

namespace Seaman\Service\ConfigParser;

use RuntimeException;
use Seaman\ValueObject\VolumeConfig;

final readonly class VolumeConfigParser
{
    /**
     * @param array<string, mixed> $data
     */
    public function parse(array $data): VolumeConfig
    {
        $volumesData = $data['volumes'] ?? [];
        if (!is_array($volumesData)) {
            throw new RuntimeException('Invalid volumes configuration');
        }

        $persistData = $volumesData['persist'] ?? [];
        if (!is_array($persistData)) {
            throw new RuntimeException('Invalid persist configuration');
        }

        return new VolumeConfig(
            persist: $this->parsePersistList($persistData),
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $basePersist
     */
    public function merge(array $data, array $basePersist): VolumeConfig
    {
        $volumesData = $data['volumes'] ?? [];
        if (!is_array($volumesData)) {
            $volumesData = [];
        }

        $persistData = $volumesData['persist'] ?? $basePersist;
        if (!is_array($persistData)) {
            $persistData = $basePersist;
        }

        return new VolumeConfig(
            persist: $this->parsePersistList($persistData),
        );
    }

    /**
     * @param array<int|string, mixed> $persistData
     * @return list<string>
     */
    private function parsePersistList(array $persistData): array
    {
        /** @var list<string> $persistList */
        $persistList = [];
        foreach ($persistData as $volume) {
            if (is_string($volume)) {
                $persistList[] = $volume;
            }
        }

        return $persistList;
    }
}
