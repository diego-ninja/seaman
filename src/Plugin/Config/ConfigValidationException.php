<?php

// ABOUTME: Exception thrown when plugin configuration validation fails.
// ABOUTME: Contains field name and validation error details.

declare(strict_types=1);

namespace Seaman\Plugin\Config;

use Seaman\Exception\SeamanException;

final class ConfigValidationException extends SeamanException
{
    public static function invalidValue(string $field, string $reason): self
    {
        return new self("Invalid value for '{$field}': {$reason}");
    }

    public static function unknownField(string $field): self
    {
        return new self("Unknown configuration field: '{$field}'");
    }
}
