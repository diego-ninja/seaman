<?php

declare(strict_types=1);

// ABOUTME: Value object collection for custom (unrecognized) services.
// ABOUTME: Immutable collection storing raw docker-compose service configurations.

namespace Seaman\ValueObject;

final readonly class CustomServiceCollection
{
    /**
     * @param array<string, array<string, mixed>> $services
     */
    public function __construct(
        private array $services = [],
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->services;
    }

    public function has(string $name): bool
    {
        return isset($this->services[$name]);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $name): array
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException("Custom service '{$name}' not found");
        }

        return $this->services[$name];
    }

    public function count(): int
    {
        return count($this->services);
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function add(string $name, array $config): self
    {
        return new self([...$this->services, $name => $config]);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->services);
    }
}
