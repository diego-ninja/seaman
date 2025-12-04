<?php

declare(strict_types=1);

// ABOUTME: Main configuration value object for Seaman.
// ABOUTME: Contains PHP, services, volumes, and proxy configuration.

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
        public ?ProxyConfig $proxy = null,
    ) {}

    public function proxy(): ProxyConfig
    {
        // If no proxy config provided, create default
        return $this->proxy ?? ProxyConfig::default($this->projectName);
    }
}
