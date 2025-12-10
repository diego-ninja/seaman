<?php

declare(strict_types=1);

// ABOUTME: Exception thrown when a required system binary is not installed.
// ABOUTME: E.g., docker, docker-compose, or dns configuration tools.

namespace Seaman\Exception;

class BinaryNotFoundException extends SeamanException
{
    public static function withBinary(string $binary): self
    {
        return new self(sprintf('%s binary not found. Please install it before continue.', $binary));
    }
}
