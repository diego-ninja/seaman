<?php

declare(strict_types=1);

// ABOUTME: Result of a process execution.
// ABOUTME: Contains exit code, output, and success status.

namespace Seaman\ValueObject;

final readonly class ProcessResult
{
    public function __construct(
        public int $exitCode,
        public string $output = '',
        public string $errorOutput = '',
    ) {}

    /**
     * Check if the process execution was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }
}
