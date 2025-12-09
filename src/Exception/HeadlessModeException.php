<?php

declare(strict_types=1);

// ABOUTME: Exception thrown when headless mode lacks required values.
// ABOUTME: Occurs when a prompt has no default and no preset response.

namespace Seaman\Exception;

use RuntimeException;

final class HeadlessModeException extends RuntimeException
{
    public static function missingDefault(string $label): self
    {
        return new self(sprintf(
            'No default value for required prompt in headless mode: %s',
            $label,
        ));
    }
}
