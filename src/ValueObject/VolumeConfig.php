<?php

declare(strict_types=1);

// ABOUTME: Volume persistence configuration.
// ABOUTME: Defines which Docker volumes should persist data.

namespace Seaman\ValueObject;

use Seaman\Enum\Database;
use Seaman\Enum\Service;

readonly class VolumeConfig
{
    /**
     * @param list<Service|Database> $persist
     */
    public function __construct(
        public array $persist = [],
    ) {}

    public function shouldPersist(Service $service): bool
    {
        return in_array($service, $this->persist, true);
    }
}
