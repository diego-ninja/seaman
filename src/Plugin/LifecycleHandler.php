<?php

// ABOUTME: Represents a lifecycle event handler from a plugin.
// ABOUTME: Contains event name, priority, and callable reference.

declare(strict_types=1);

namespace Seaman\Plugin;

final readonly class LifecycleHandler
{
    /**
     * @param callable(LifecycleEventData): void $handler
     */
    public function __construct(
        public string $event,
        public int $priority,
        public mixed $handler,
    ) {}
}
