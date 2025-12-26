<?php

// ABOUTME: Exception thrown when a file operation fails.
// ABOUTME: Covers read, write, and other file system operations.

declare(strict_types=1);

namespace Seaman\Exception;

use Throwable;

final class FileOperationException extends SeamanException
{
    private string $filePath = '';
    private string $operation = '';

    public static function readFailed(string $filePath, ?Throwable $previous = null): self
    {
        $exception = new self("Failed to read file: {$filePath}", 0, $previous);
        $exception->filePath = $filePath;
        $exception->operation = 'read';

        return $exception;
    }

    public static function writeFailed(string $filePath, ?Throwable $previous = null): self
    {
        $exception = new self("Failed to write file: {$filePath}", 0, $previous);
        $exception->filePath = $filePath;
        $exception->operation = 'write';

        return $exception;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }
}
