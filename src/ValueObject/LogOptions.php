<?php

declare(strict_types=1);

// ABOUTME: Docker logs command options.
// ABOUTME: Configures log viewing behavior.

namespace Seaman\ValueObject;

final readonly class LogOptions
{
    public function __construct(
        public bool $follow = false,
        public ?int $tail = null,
        public ?string $since = null,
    ) {}
}
