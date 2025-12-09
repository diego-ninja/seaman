<?php

declare(strict_types=1);

// ABOUTME: Exception thrown when port allocation fails.
// ABOUTME: Covers user rejection and exhausted port range scenarios.

namespace Seaman\Exception;

use RuntimeException;

final class PortAllocationException extends RuntimeException
{
    public static function noPortsAvailable(string $service, int $startPort): self
    {
        return new self(sprintf(
            'No available ports found for "%s" (tried %d to %d)',
            $service,
            $startPort,
            $startPort + 10,
        ));
    }

    public static function userRejected(string $service, int $port): self
    {
        return new self(sprintf(
            'Port allocation for "%s" (port %d) was rejected',
            $service,
            $port,
        ));
    }
}
