<?php

// ABOUTME: Attribute to mark a method as a lifecycle event handler.
// ABOUTME: Method receives LifecycleEventData with context about the event.

declare(strict_types=1);

namespace Seaman\Plugin\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class OnLifecycle
{
    public function __construct(
        public string $event,
        public int $priority = 0,
    ) {}
}
