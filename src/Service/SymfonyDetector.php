<?php

// ABOUTME: Detects Symfony applications using multiple indicators.
// ABOUTME: Requires 2-3 indicators to confirm valid Symfony project.

declare(strict_types=1);

namespace Seaman\Service;

use Seaman\ValueObject\SymfonyDetectionResult;

final readonly class SymfonyDetector
{
    /**
     * Detect if directory contains a Symfony application.
     *
     * @param string $directory Path to check
     * @return SymfonyDetectionResult Detection result with matched indicators count
     */
    public function detect(string $directory): SymfonyDetectionResult
    {
        $indicators = 0;

        // Indicator 1: composer.json with symfony/framework-bundle
        if ($this->hasFrameworkBundle($directory)) {
            $indicators++;
        }

        // Indicator 2: bin/console exists and is executable
        if ($this->hasConsoleScript($directory)) {
            $indicators++;
        }

        // Indicator 3: config/ directory exists
        if (is_dir($directory . '/config')) {
            $indicators++;
        }

        // Indicator 4: src/Kernel.php exists
        if (file_exists($directory . '/src/Kernel.php')) {
            $indicators++;
        }

        // Require 2-3 indicators for positive detection
        $isSymfonyProject = $indicators >= 2;

        return new SymfonyDetectionResult($isSymfonyProject, $indicators);
    }

    private function hasFrameworkBundle(string $directory): bool
    {
        $composerFile = $directory . '/composer.json';

        if (!file_exists($composerFile)) {
            return false;
        }

        $content = file_get_contents($composerFile);
        if ($content === false) {
            return false;
        }

        /** @var mixed $data */
        $data = json_decode($content, true);

        if (!is_array($data)) {
            return false;
        }

        if (!isset($data['require']) || !is_array($data['require'])) {
            return false;
        }

        return isset($data['require']['symfony/framework-bundle']);
    }

    private function hasConsoleScript(string $directory): bool
    {
        $consolePath = $directory . '/bin/console';

        return file_exists($consolePath) && is_executable($consolePath);
    }
}
