<?php

// ABOUTME: Value object containing event listener metadata.
// ABOUTME: Used for registering listeners with EventDispatcher.

declare(strict_types=1);

namespace Seaman\EventListener;

final readonly class EventListenerMetadata
{
    /**
     * Create event listener metadata.
     *
     * @param string $className Fully qualified class name
     * @param string $event Event name
     * @param int $priority Execution priority
     */
    public function __construct(
        public string $className,
        public string $event,
        public int $priority,
    ) {}
}
