<?php

// ABOUTME: Defines a Docker service provided by a plugin.
// ABOUTME: Contains template path, config parser, and default configuration.

declare(strict_types=1);

namespace Seaman\Plugin;

final readonly class ServiceDefinition
{
    /**
     * @param array<string, mixed> $defaultConfig
     */
    public function __construct(
        public string $name,
        public string $template,
        public array $defaultConfig = [],
        public ?string $configParser = null,
    ) {}
}
