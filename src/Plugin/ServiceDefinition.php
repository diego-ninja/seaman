<?php

// ABOUTME: Defines a Docker service provided by a plugin.
// ABOUTME: Contains all metadata needed for ServiceRegistry integration.

declare(strict_types=1);

namespace Seaman\Plugin;

use Seaman\Enum\ServiceCategory;
use Seaman\ValueObject\HealthCheck;

final readonly class ServiceDefinition
{
    /**
     * @param array<string, mixed> $defaultConfig
     * @param list<int> $ports
     * @param list<int> $internalPorts
     * @param list<string> $dependencies
     */
    public function __construct(
        public string $name,
        public string $template,
        public array $defaultConfig = [],
        public ?string $configParser = null,
        public ?string $displayName = null,
        public string $description = 'Plugin-provided service',
        public string $icon = '🔌',
        public ServiceCategory $category = ServiceCategory::Misc,
        public array $ports = [],
        public array $internalPorts = [],
        public array $dependencies = [],
        public ?HealthCheck $healthCheck = null,
        public ?DatabaseOperations $databaseOperations = null,
    ) {}

    public function getDisplayName(): string
    {
        return $this->displayName ?? ucwords(str_replace('-', ' ', $this->name));
    }
}
