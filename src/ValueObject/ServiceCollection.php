<?php

declare(strict_types=1);

// ABOUTME: Collection of service configurations.
// ABOUTME: Provides immutable operations for managing services.

namespace Seaman\ValueObject;

readonly class ServiceCollection
{
    /**
     * @param array<string, ServiceConfig> $services
     */
    public function __construct(
        private array $services = [],
    ) {}

    /**
     * @return array<string, ServiceConfig>
     */
    public function all(): array
    {
        return $this->services;
    }

    /**
     * @return array<string, ServiceConfig>
     */
    public function enabled(): array
    {
        return array_filter(
            $this->services,
            fn(ServiceConfig $service): bool => $service->enabled,
        );
    }

    public function count(): int
    {
        return count($this->services);
    }

    public function has(string $name): bool
    {
        return isset($this->services[$name]);
    }

    public function get(string $name): ServiceConfig
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException("Service '{$name}' not found");
        }

        return $this->services[$name];
    }

    public function add(string $name, ServiceConfig $service): self
    {
        return new self([...$this->services, $name => $service]);
    }

    public function remove(string $name): self
    {
        $services = $this->services;
        unset($services[$name]);

        return new self($services);
    }
}
