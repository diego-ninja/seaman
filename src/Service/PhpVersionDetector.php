<?php

declare(strict_types=1);

// ABOUTME: Detects PHP version from project files.
// ABOUTME: Supports detection from composer.json and other sources.

namespace Seaman\Service;

use Seaman\Enum\PhpVersion;

class PhpVersionDetector
{
    /**
     * Detect PHP version from composer.json file.
     */
    public function detectFromComposer(string $projectRoot): ?PhpVersion
    {
        $composerPath = $projectRoot . '/composer.json';
        if (!file_exists($composerPath)) {
            return null;
        }

        $composerContent = file_get_contents($composerPath);
        if ($composerContent === false) {
            return null;
        }

        /** @var mixed $composer */
        $composer = json_decode($composerContent, true);
        if (!is_array($composer)) {
            return null;
        }

        /** @var array<string, mixed> $composer */
        $require = $composer['require'] ?? null;
        if (!is_array($require)) {
            return null;
        }

        $phpRequirement = $require['php'] ?? null;
        if (!is_string($phpRequirement)) {
            return null;
        }

        // Parse PHP version from requirement like "^8.4", ">=8.3", "~8.4.0", etc.
        if (preg_match('/(\d+\.\d+)/', $phpRequirement, $matches)) {
            $versionString = $matches[1];
            $phpVersion = PhpVersion::tryFrom($versionString);

            // If detected version is supported, return it
            if ($phpVersion !== null && PhpVersion::isSupported($phpVersion)) {
                return $phpVersion;
            }
        }

        return null;
    }

    /**
     * Detect PHP version from all available sources.
     * Currently only supports composer.json, but extensible for future sources.
     */
    public function detect(string $projectRoot): ?PhpVersion
    {
        // Try composer.json first
        $version = $this->detectFromComposer($projectRoot);
        if ($version !== null) {
            return $version;
        }

        // Future: Try .php-version file, .tool-versions, etc.

        return null;
    }
}
