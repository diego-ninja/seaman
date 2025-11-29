<?php

declare(strict_types=1);

// ABOUTME: Service configuration value object.
// ABOUTME: Represents configuration for a single Docker service.

namespace Seaman\ValueObject;

final readonly class ServiceConfig
{
    /**
     * @param list<int> $additionalPorts
     * @param array<string, string> $environmentVariables
     */
    public function __construct(
        public string $name,
        public bool $enabled,
        public string $type,
        public string $version,
        public int $port,
        public array $additionalPorts,
        public array $environmentVariables,
    ) {}

    /**
     * @return list<int>
     */
    public function getAllPorts(): array
    {
        return [$this->port, ...$this->additionalPorts];
    }
}
