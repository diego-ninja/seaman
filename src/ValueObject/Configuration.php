<?php

declare(strict_types=1);

// ABOUTME: Main configuration value object for Seaman.
// ABOUTME: Contains PHP, services, volumes, and proxy configuration.

namespace Seaman\ValueObject;

use Seaman\Enum\ProjectType;

final readonly class Configuration
{
    /**
     * @param array<string, array<string, mixed>> $plugins
     */
    public function __construct(
        public string $projectName,
        public string $version,
        public PhpConfig $php,
        public ServiceCollection $services,
        public VolumeConfig $volumes,
        public ProjectType $projectType = ProjectType::Existing,
        public ?ProxyConfig $proxy = null,
        public CustomServiceCollection $customServices = new CustomServiceCollection(),
        public array $plugins = [],
    ) {}

    public function proxy(): ProxyConfig
    {
        // If no proxy config provided, create default
        return $this->proxy ?? ProxyConfig::default($this->projectName);
    }

    public function hasCustomServices(): bool
    {
        return !$this->customServices->isEmpty();
    }

    /**
     * Creates a copy of this configuration with updated fields.
     *
     * @param array<string, array<string, mixed>>|null $plugins
     */
    public function with(
        ?string $projectName = null,
        ?string $version = null,
        ?PhpConfig $php = null,
        ?ServiceCollection $services = null,
        ?VolumeConfig $volumes = null,
        ?ProjectType $projectType = null,
        ?ProxyConfig $proxy = null,
        ?CustomServiceCollection $customServices = null,
        ?array $plugins = null,
    ): self {
        return new self(
            projectName: $projectName ?? $this->projectName,
            version: $version ?? $this->version,
            php: $php ?? $this->php,
            services: $services ?? $this->services,
            volumes: $volumes ?? $this->volumes,
            projectType: $projectType ?? $this->projectType,
            proxy: $proxy ?? $this->proxy,
            customServices: $customServices ?? $this->customServices,
            plugins: $plugins ?? $this->plugins,
        );
    }
}
