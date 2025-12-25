<?php

// ABOUTME: Exception thrown when YAML parsing fails.
// ABOUTME: Wraps Symfony YAML parser errors with file context.

declare(strict_types=1);

namespace Seaman\Exception;

use Throwable;

final class YamlParseException extends SeamanException
{
    private string $filePath = '';

    public static function create(string $filePath, string $parseError, ?Throwable $previous = null): self
    {
        $exception = new self(
            "Failed to parse YAML in {$filePath}: {$parseError}",
            0,
            $previous,
        );
        $exception->filePath = $filePath;

        return $exception;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }
}
