<?php

// ABOUTME: Interface for commands that support mode-based filtering.
// ABOUTME: Allows Application to query which modes a command supports.

declare(strict_types=1);

namespace Seaman\Contract;

use Seaman\Enum\OperatingMode;

interface ModeAwareInterface
{
    /**
     * Determines if this command supports the given operating mode.
     */
    public function supportsMode(OperatingMode $mode): bool;
}
