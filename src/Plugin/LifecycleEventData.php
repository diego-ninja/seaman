<?php

// ABOUTME: Event data passed to lifecycle event handlers.
// ABOUTME: Contains context about the current operation.

declare(strict_types=1);

namespace Seaman\Plugin;

final readonly class LifecycleEventData
{
    public function __construct(
        public string $event,
        public string $projectRoot,
        public ?string $service = null,
    ) {}
}
