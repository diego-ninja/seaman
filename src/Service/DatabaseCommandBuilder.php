<?php

declare(strict_types=1);

// ABOUTME: Builds database CLI commands for dump, restore, and shell operations.
// ABOUTME: Supports PostgreSQL, MySQL, MariaDB, SQLite, and MongoDB.

namespace Seaman\Service;

use Seaman\Enum\Service;
use Seaman\ValueObject\ServiceConfig;

final readonly class DatabaseCommandBuilder
{
    /**
     * Builds a dump command for the given database service.
     *
     * @return list<string>|null The command array, or null if unsupported
     */
    public function dump(ServiceConfig $config): ?array
    {
        /** @var array<string, string> $env */
        $env = $config->environmentVariables;

        return match ($config->type) {
            Service::PostgreSQL => [
                'pg_dump',
                '-U',
                $env['POSTGRES_USER'] ?? 'postgres',
                $env['POSTGRES_DB'] ?? 'postgres',
            ],
            Service::MySQL, Service::MariaDB => [
                'mysqldump',
                '-u',
                $env['MYSQL_USER'] ?? 'root',
                '-p' . ($env['MYSQL_PASSWORD'] ?? ''),
                $env['MYSQL_DATABASE'] ?? 'mysql',
            ],
            Service::SQLite => [
                'sqlite3',
                $env['SQLITE_DATABASE'] ?? '/data/database.db',
                '.dump',
            ],
            Service::MongoDB => [
                'mongodump',
                '--username',
                $env['MONGO_INITDB_ROOT_USERNAME'] ?? 'root',
                '--password',
                $env['MONGO_INITDB_ROOT_PASSWORD'] ?? '',
                '--authenticationDatabase',
                'admin',
                '--archive',
            ],
            default => null,
        };
    }

    /**
     * Builds a restore command for the given database service.
     *
     * @return list<string>|null The command array, or null if unsupported
     */
    public function restore(ServiceConfig $config): ?array
    {
        /** @var array<string, string> $env */
        $env = $config->environmentVariables;

        return match ($config->type) {
            Service::PostgreSQL => [
                'psql',
                '-U',
                $env['POSTGRES_USER'] ?? 'postgres',
                $env['POSTGRES_DB'] ?? 'postgres',
            ],
            Service::MySQL, Service::MariaDB => [
                'mysql',
                '-u',
                $env['MYSQL_USER'] ?? 'root',
                '-p' . ($env['MYSQL_PASSWORD'] ?? ''),
                $env['MYSQL_DATABASE'] ?? 'mysql',
            ],
            Service::SQLite => [
                'sqlite3',
                $env['SQLITE_DATABASE'] ?? '/data/database.db',
            ],
            Service::MongoDB => [
                'mongorestore',
                '--username',
                $env['MONGO_INITDB_ROOT_USERNAME'] ?? 'root',
                '--password',
                $env['MONGO_INITDB_ROOT_PASSWORD'] ?? '',
                '--authenticationDatabase',
                'admin',
                '--archive',
                '--drop',
            ],
            default => null,
        };
    }

    /**
     * Builds a shell command for the given database service.
     *
     * @return list<string>|null The command array, or null if unsupported
     */
    public function shell(ServiceConfig $config): ?array
    {
        /** @var array<string, string> $env */
        $env = $config->environmentVariables;

        return match ($config->type) {
            Service::PostgreSQL => [
                'psql',
                '-U',
                $env['POSTGRES_USER'] ?? 'postgres',
                $env['POSTGRES_DB'] ?? 'postgres',
            ],
            Service::MySQL, Service::MariaDB => [
                'mysql',
                '-u',
                $env['MYSQL_USER'] ?? 'root',
                '-p' . ($env['MYSQL_PASSWORD'] ?? ''),
                $env['MYSQL_DATABASE'] ?? 'mysql',
            ],
            Service::SQLite => [
                'sqlite3',
                $env['SQLITE_DATABASE'] ?? '/data/database.db',
            ],
            Service::MongoDB => [
                'mongosh',
                '--username',
                $env['MONGO_INITDB_ROOT_USERNAME'] ?? 'root',
                '--password',
                $env['MONGO_INITDB_ROOT_PASSWORD'] ?? '',
                '--authenticationDatabase',
                'admin',
            ],
            default => null,
        };
    }
}
