<?php

// ABOUTME: Enum representing seaman's operating modes.
// ABOUTME: Determines what features are available based on configuration state.

declare(strict_types=1);

namespace Seaman\Enum;

enum OperatingMode
{
    case Managed;       // .seaman/seaman.yaml exists - full features
    case Unmanaged;     // Only docker-compose.yaml exists - basic passthrough
    case Uninitialized; // Neither exists - must run init

    public function requiresInitialization(): bool
    {
        return $this === self::Uninitialized;
    }

    public function isManaged(): bool
    {
        return $this === self::Managed;
    }

    public function label(): string
    {
        return match ($this) {
            self::Managed => 'Managed (Full Features)',
            self::Unmanaged => 'Unmanaged (Basic Commands)',
            self::Uninitialized => 'Not Initialized',
        };
    }
}
