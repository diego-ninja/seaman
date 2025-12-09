<?php

// ABOUTME: Interface for database services that support dump/restore/shell operations.
// ABOUTME: Implemented by MySQL, PostgreSQL, MariaDB, MongoDB, and SQLite services.

declare(strict_types=1);

namespace Seaman\Contract;

use Seaman\ValueObject\ServiceConfig;

interface DatabaseServiceInterface
{
    /**
     * Returns the command to dump the database to stdout.
     *
     * @return list<string>
     */
    public function getDumpCommand(ServiceConfig $config): array;

    /**
     * Returns the command to restore the database from stdin.
     *
     * @return list<string>
     */
    public function getRestoreCommand(ServiceConfig $config): array;

    /**
     * Returns the command to open an interactive database shell.
     *
     * @return list<string>
     */
    public function getShellCommand(ServiceConfig $config): array;
}
