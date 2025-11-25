<?php

declare(strict_types=1);

// ABOUTME: Volume persistence configuration.
// ABOUTME: Defines which Docker volumes should persist data.

namespace Seaman\ValueObject;

readonly class VolumeConfig
{
    /**
     * @param list<string> $persist
     */
    public function __construct(
        public array $persist = [],
    ) {}

    public function shouldPersist(string $volumeName): bool
    {
        return in_array($volumeName, $this->persist, true);
    }
}
