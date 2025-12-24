<?php

// ABOUTME: Value object for database operations command templates.
// ABOUTME: Used by database plugins to define dump, restore, and shell commands.

declare(strict_types=1);

namespace Seaman\Plugin;

use Seaman\ValueObject\ServiceConfig;

final readonly class DatabaseOperations
{
    /**
     * @param \Closure(ServiceConfig): list<string> $dumpCommand
     * @param \Closure(ServiceConfig): list<string> $restoreCommand
     * @param \Closure(ServiceConfig): list<string> $shellCommand
     */
    public function __construct(
        private \Closure $dumpCommand,
        private \Closure $restoreCommand,
        private \Closure $shellCommand,
    ) {}

    /**
     * @return list<string>
     */
    public function getDumpCommand(ServiceConfig $config): array
    {
        return ($this->dumpCommand)($config);
    }

    /**
     * @return list<string>
     */
    public function getRestoreCommand(ServiceConfig $config): array
    {
        return ($this->restoreCommand)($config);
    }

    /**
     * @return list<string>
     */
    public function getShellCommand(ServiceConfig $config): array
    {
        return ($this->shellCommand)($config);
    }
}
