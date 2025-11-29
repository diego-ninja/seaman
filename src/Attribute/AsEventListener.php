<?php

// ABOUTME: Attribute to mark event listener classes.
// ABOUTME: Specifies event name and execution priority.

declare(strict_types=1);

namespace Seaman\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsEventListener
{
    /**
     * Mark a class as an event listener.
     *
     * @param string $event Event name (e.g., ConsoleEvents::COMMAND)
     * @param int $priority Execution priority (higher = earlier, default: 0)
     */
    public function __construct(
        public string $event,
        public int $priority = 0,
    ) {}
}
