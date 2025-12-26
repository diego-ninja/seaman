<?php

// ABOUTME: Exception thrown when a required file does not exist.
// ABOUTME: Includes the file path for debugging purposes.

declare(strict_types=1);

namespace Seaman\Exception;

final class FileNotFoundException extends SeamanException
{
    private string $filePath = '';

    public static function create(string $filePath, string $message = ''): self
    {
        $msg = $message !== '' ? $message : "File not found: {$filePath}";
        $exception = new self($msg);
        $exception->filePath = $filePath;

        return $exception;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }
}
