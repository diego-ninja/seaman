<?php

declare(strict_types=1);

// ABOUTME: Process execution result value object.
// ABOUTME: Captures command execution output and status.

namespace Seaman\ValueObject;

final readonly class ProcessResult
{
    public function __construct(
        public int $exitCode,
        public string $output,
        public string $errorOutput,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }
}
