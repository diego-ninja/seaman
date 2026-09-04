<?php

declare(strict_types=1);

// ABOUTME: Deterministic command executor fixture for certificate tests.
// ABOUTME: Models mkcert availability without invoking local binaries.

namespace Seaman\Tests\Unit\Service\Process;

use Seaman\Contract\CommandExecutor;
use Seaman\ValueObject\ProcessResult;

final readonly class FakeCommandExecutor implements CommandExecutor
{
    public function __construct(private bool $mkcertAvailable = true) {}

    public function execute(array $command): ProcessResult
    {
        if ($command[0] === 'which' && $command[1] === 'mkcert') {
            return new ProcessResult(exitCode: $this->mkcertAvailable ? 0 : 1);
        }

        return new ProcessResult(exitCode: 0);
    }
}
