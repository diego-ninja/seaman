<?php

declare(strict_types=1);

// ABOUTME: Xdebug configuration value object.
// ABOUTME: Contains Xdebug-specific settings.

namespace Seaman\ValueObject;

final readonly class XdebugConfig
{
    public function __construct(
        public bool $enabled,
        public string $ideKey,
        public string $clientHost,
    ) {}
}
