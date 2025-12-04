<?php

declare(strict_types=1);

// ABOUTME: Interface for executing shell commands.
// ABOUTME: Allows testing without actual command execution.

namespace Seaman\Contract;

use Seaman\ValueObject\ProcessResult;

interface CommandExecutor
{
    /**
     * Execute a command.
     *
     * @param list<string> $command Command and arguments
     */
    public function execute(array $command): ProcessResult;
}
