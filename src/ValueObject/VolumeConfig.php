<?php

declare(strict_types=1);

// ABOUTME: Volume persistence configuration.
// ABOUTME: Defines which Docker volumes should persist data.

namespace Seaman\ValueObject;

use Seaman\Enum\Service;

final readonly class VolumeConfig
{
    /** @var list<string> */
    public array $persist;

    /**
     * @param list<Service|string> $persist
     */
    public function __construct(
        array $persist = [],
    ) {
        $this->persist = array_map(function (Service|string $item): string {
            return $item instanceof Service ? $item->value : $item;
        }, $persist);
    }

    public function shouldPersist(Service|string $service): bool
    {
        $serviceName = $service instanceof Service ? $service->value : $service;
        return in_array($serviceName, $this->persist, true);
    }
}
