<?php

declare(strict_types=1);

// ABOUTME: Exception thrown when .env file is not found.
// ABOUTME: Provides factory method for consistent error messages.

namespace Seaman\Environment\Exception;

use RuntimeException;

class EnvironmentNotFoundException extends RuntimeException
{
    public static function forEnv(string $env_file): self
    {
        return new self(
            message: sprintf(
                'Environment file "%s" not found.',
                $env_file,
            ),
        );
    }
}
