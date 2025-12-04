<?php

declare(strict_types=1);

// ABOUTME: Main configuration value object for Seaman.
// ABOUTME: Contains PHP, services, and volumes configuration.

namespace Seaman\ValueObject;

use Seaman\Enum\ProjectType;

final readonly class Configuration
{
    public function __construct(
        public string $projectName,
        public string $version,
        public PhpConfig $php,
        public ServiceCollection $services,
        public VolumeConfig $volumes,
        public ProjectType $projectType = ProjectType::Existing,
    ) {}
}
