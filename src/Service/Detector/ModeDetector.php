<?php

// ABOUTME: Detects seaman's current operating mode.
// ABOUTME: Checks for seaman.yaml and docker-compose.yaml to determine mode.

declare(strict_types=1);

namespace Seaman\Service\Detector;

use Seaman\Enum\OperatingMode;
use Seaman\Service\ComposeFileLocator;

final readonly class ModeDetector
{
    public function __construct(
        private string $projectRoot = '.',
    ) {}

    public function detect(): OperatingMode
    {
        $seamanConfigPath = $this->projectRoot . '/.seaman/seaman.yaml';

        // Check for seaman.yaml first (managed mode)
        if (file_exists($seamanConfigPath)) {
            return OperatingMode::Managed;
        }

        // Check for docker-compose.yaml (unmanaged mode)
        if ((new ComposeFileLocator($this->projectRoot))->find() !== null) {
            return OperatingMode::Unmanaged;
        }

        // Neither exists (uninitialized)
        return OperatingMode::Uninitialized;
    }

    public function isManaged(): bool
    {
        return $this->detect() === OperatingMode::Managed;
    }

    public function requiresInitialization(): bool
    {
        return $this->detect()->requiresInitialization();
    }
}
