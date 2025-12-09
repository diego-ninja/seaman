<?php

declare(strict_types=1);

// ABOUTME: Maps desired ports to actually assigned ports.
// ABOUTME: Tracks port allocations when defaults are occupied.

namespace Seaman\ValueObject;

final readonly class PortAllocation
{
    /**
     * @param array<string, array<int, int>> $allocations
     *        Map: serviceName => [desiredPort => assignedPort]
     */
    public function __construct(
        private array $allocations = [],
    ) {}

    /**
     * Create a new allocation with an additional port mapping.
     */
    public function withPort(string $service, int $desired, int $assigned): self
    {
        $newAllocations = $this->allocations;
        $newAllocations[$service][$desired] = $assigned;

        return new self($newAllocations);
    }

    /**
     * Get the assigned port for a service.
     * Returns the desired port if no allocation exists.
     */
    public function getPort(string $service, int $desiredPort): int
    {
        return $this->allocations[$service][$desiredPort] ?? $desiredPort;
    }

    /**
     * Check if any port was assigned differently from desired.
     */
    public function hasAlternatives(): bool
    {
        foreach ($this->allocations as $ports) {
            foreach ($ports as $desired => $assigned) {
                if ($desired !== $assigned) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get all allocations.
     *
     * @return array<string, array<int, int>>
     */
    public function all(): array
    {
        return $this->allocations;
    }
}
