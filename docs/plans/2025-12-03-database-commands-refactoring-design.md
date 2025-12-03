# Database Commands Refactoring Design

**Date:** 2025-12-03
**Author:** Diego & Claude
**Status:** Approved

## Overview

Refactor the three database management commands (`db:shell`, `db:dump`, `db:restore`) to align with codebase patterns while adding SQLite and MongoDB support.

## Goals

1. Achieve consistency with existing command patterns (Laravel Prompts, Decorable, Terminal)
2. Add SQLite and MongoDB support to all database commands
3. Improve UX with spinners for long-running operations
4. Enable flexible database selection when multiple databases are configured

## Architecture

### Command Structure

All three commands will:
- Extend `AbstractSeamanCommand`
- Implement `Decorable` interface
- Use `Terminal` class for output
- Use Laravel Prompts for user interaction
- Support all database types via `Service` enum

### Supported Databases

- **PostgreSQL**: psql, pg_dump, psql restore (existing)
- **MySQL/MariaDB**: mysql, mysqldump, mysql restore (existing)
- **SQLite**: sqlite3, .dump export, SQL import (new)
- **MongoDB**: mongosh, mongodump, mongorestore (new)

## Database Service Discovery & Selection

### Selection Logic

1. Load configuration using `ConfigManager`
2. Find all enabled database services using `Service::databases()`
3. Filter out `Service::None`
4. Apply selection:
   - If `--service` option provided: Use that specific service or error
   - If only one database found: Use automatically
   - If multiple databases found: Use Laravel Prompts `select()` to ask user
   - If no databases found: Error with suggestion to run `seaman service:add`

### Shared Method Pattern

Each command will include a private `selectDatabaseService()` method:

```php
private function selectDatabaseService(Configuration $config, ?string $serviceName): ?ServiceConfig
{
    $databases = array_filter(
        $config->services->all(),
        fn(ServiceConfig $s) => in_array($s->type->value, Service::databases(), true)
            && $s->type !== Service::None
    );

    if ($serviceName !== null) {
        $service = array_find($databases, fn(ServiceConfig $s) => $s->name === $serviceName);
        if ($service === null) {
            throw new \RuntimeException("Service '{$serviceName}' not found");
        }
        return $service;
    }

    if (count($databases) === 0) {
        return null;
    }

    if (count($databases) === 1) {
        return array_values($databases)[0];
    }

    // Multiple databases - ask user
    $choices = [];
    foreach ($databases as $db) {
        $choices[$db->name] = sprintf('%s (%s)', $db->name, $db->type->value);
    }

    $selected = select(
        label: 'Select database service:',
        options: $choices,
    );

    return array_find($databases, fn(ServiceConfig $s) => $s->name === $selected);
}
```

## Database-Specific Commands

### Shell Commands (db:shell)

Interactive database client access:

```php
private function getShellCommand(ServiceConfig $service): ?array
{
    $envVars = $service->environmentVariables;

    return match ($service->type) {
        Service::PostgreSQL => [
            'psql',
            '-U', $envVars['POSTGRES_USER'] ?? 'postgres',
            $envVars['POSTGRES_DB'] ?? 'postgres',
        ],
        Service::MySQL, Service::MariaDB => [
            'mysql',
            '-u', $envVars['MYSQL_USER'] ?? 'root',
            '-p' . ($envVars['MYSQL_PASSWORD'] ?? ''),
            $envVars['MYSQL_DATABASE'] ?? 'mysql',
        ],
        Service::SQLite => [
            'sqlite3',
            $envVars['SQLITE_DATABASE'] ?? '/data/database.db',
        ],
        Service::MongoDB => [
            'mongosh',
            '--username', $envVars['MONGO_INITDB_ROOT_USERNAME'] ?? 'root',
            '--password', $envVars['MONGO_INITDB_ROOT_PASSWORD'] ?? '',
            '--authenticationDatabase', 'admin',
        ],
        default => null,
    };
}
```

### Dump Commands (db:dump)

Export database to file:

```php
private function getDumpCommand(ServiceConfig $service): ?array
{
    $envVars = $service->environmentVariables;

    return match ($service->type) {
        Service::PostgreSQL => [
            'pg_dump',
            '-U', $envVars['POSTGRES_USER'] ?? 'postgres',
            $envVars['POSTGRES_DB'] ?? 'postgres',
        ],
        Service::MySQL, Service::MariaDB => [
            'mysqldump',
            '-u', $envVars['MYSQL_USER'] ?? 'root',
            '-p' . ($envVars['MYSQL_PASSWORD'] ?? ''),
            $envVars['MYSQL_DATABASE'] ?? 'mysql',
        ],
        Service::SQLite => [
            'sqlite3',
            $envVars['SQLITE_DATABASE'] ?? '/data/database.db',
            '.dump',
        ],
        Service::MongoDB => [
            'mongodump',
            '--username', $envVars['MONGO_INITDB_ROOT_USERNAME'] ?? 'root',
            '--password', $envVars['MONGO_INITDB_ROOT_PASSWORD'] ?? '',
            '--authenticationDatabase', 'admin',
            '--archive',
        ],
        default => null,
    };
}
```

**File naming**: Default to `{service_name}_dump_{YYYYMMDD_HHMMSS}.{ext}` where ext is:
- `.sql` for PostgreSQL, MySQL, MariaDB, SQLite
- `.archive` for MongoDB

### Restore Commands (db:restore)

Import database from file:

```php
private function getRestoreCommand(ServiceConfig $service): ?array
{
    $envVars = $service->environmentVariables;

    return match ($service->type) {
        Service::PostgreSQL => [
            'psql',
            '-U', $envVars['POSTGRES_USER'] ?? 'postgres',
            $envVars['POSTGRES_DB'] ?? 'postgres',
        ],
        Service::MySQL, Service::MariaDB => [
            'mysql',
            '-u', $envVars['MYSQL_USER'] ?? 'root',
            '-p' . ($envVars['MYSQL_PASSWORD'] ?? ''),
            $envVars['MYSQL_DATABASE'] ?? 'mysql',
        ],
        Service::SQLite => [
            'sqlite3',
            $envVars['SQLITE_DATABASE'] ?? '/data/database.db',
        ],
        Service::MongoDB => [
            'mongorestore',
            '--username', $envVars['MONGO_INITDB_ROOT_USERNAME'] ?? 'root',
            '--password', $envVars['MONGO_INITDB_ROOT_PASSWORD'] ?? '',
            '--authenticationDatabase', 'admin',
            '--archive',
            '--drop', // Drop existing collections before restoring
        ],
        default => null,
    };
}
```

**Input handling**: All restore commands read from stdin via pipe.

## UI/UX Improvements

### Consistent User Interaction

Replace all `SymfonyStyle` usage with modern patterns:

**DbShellCommand:**
- `$io->info()` → `info()` (Laravel Prompts)
- `$io->error()` → `Terminal::error()`
- Add `select()` for database selection

**DbDumpCommand:**
- Already uses `Terminal::error()` (keep)
- Wrap execution in `SpinnerFactory::for()` for progress indication
- Keep `Terminal::success()` (already correct via spinner)

**DbRestoreCommand:**
- `$io->error()` → `Terminal::error()`
- `$io->info()` → `info()` (Laravel Prompts)
- `$io->confirm()` → `confirm()` (Laravel Prompts)
- `$io->success()` → `Terminal::success()`
- Wrap execution in `SpinnerFactory::for()` for progress indication

### Progress Indication

Use existing `Spinner` infrastructure for long-running operations:

```php
// For dump
SpinnerFactory::for(
    callable: fn() => $this->executeDump($service, $file),
    message: "Dumping {$service->name} database...",
);

// For restore
SpinnerFactory::for(
    callable: fn() => $this->executeRestore($service, $file),
    message: "Restoring {$service->name} database...",
);
```

## Error Handling & Edge Cases

### Configuration Errors

```php
try {
    $config = $this->configManager->load();
} catch (\RuntimeException $e) {
    Terminal::error('Failed to load configuration: ' . $e->getMessage());
    return Command::FAILURE;
}
```

### No Database Services

```php
if ($service === null) {
    Terminal::error('No database service found in configuration.');
    Terminal::output()->writeln('Add a database service with: seaman service:add');
    return Command::FAILURE;
}
```

### Service Not Enabled

```php
if (!$service->enabled) {
    Terminal::error("Database service '{$service->name}' is not enabled.");
    return Command::FAILURE;
}
```

### Invalid Service Name

When `--service` flag provided but service not found:

```php
Terminal::error("Service '{$serviceName}' not found.");
Terminal::output()->writeln('Available databases: ' . implode(', ', $availableNames));
return Command::FAILURE;
```

### Unsupported Database Type

```php
if ($command === null) {
    Terminal::error("Unsupported database type: {$service->type->value}");
    return Command::FAILURE;
}
```

### Docker Execution Errors

```php
try {
    $result = $this->dockerManager->executeInService(...);
} catch (\RuntimeException $e) {
    Terminal::error($e->getMessage());
    return Command::FAILURE;
}
```

### Restore Safety

Maintain existing safety measures:

```php
if (!confirm(
    label: "This will overwrite the '{$service->name}' database. Continue?",
    default: false
)) {
    info('Restore cancelled.');
    return Command::SUCCESS;
}
```

### File Validation

```php
if (!file_exists($file)) {
    Terminal::error("Dump file not found: {$file}");
    return Command::FAILURE;
}

$dumpContent = file_get_contents($file);
if ($dumpContent === false) {
    Terminal::error("Failed to read dump file: {$file}");
    return Command::FAILURE;
}
```

## Command Options

### db:shell

```
Options:
  --service[=SERVICE]  Database service name (prompts if multiple exist)
```

### db:dump

```
Arguments:
  file                 Output file path [default: {service}_dump_{timestamp}.sql]

Options:
  --service[=SERVICE]  Database service name (prompts if multiple exist)
```

### db:restore

```
Arguments:
  file                 Database dump file to restore [required]

Options:
  --service[=SERVICE]  Database service name (prompts if multiple exist)
```

## Implementation Notes

### Type Safety

- All code must pass PHPStan level 10
- Use `Service` enum for type-safe database type checking
- Proper type declarations for all method parameters and return types
- PHPDoc annotations for complex types

### Code Style

- Run php-cs-fixer after all changes
- Follow PER coding standards
- Use `declare(strict_types=1);` in all files
- Include ABOUTME comments

### Testing

- Write tests following TDD (Red-Green-Refactor)
- Test all database types (PostgreSQL, MySQL, MariaDB, SQLite, MongoDB)
- Test service selection logic (single, multiple, none)
- Test error cases (missing service, disabled service, invalid files)
- Achieve 95%+ test coverage

### Environment Variables

**SQLite:**
- `SQLITE_DATABASE`: Path to database file (default: `/data/database.db`)

**MongoDB:**
- `MONGO_INITDB_ROOT_USERNAME`: Admin username (default: `root`)
- `MONGO_INITDB_ROOT_PASSWORD`: Admin password (default: empty)

## Migration Path

### Existing Code

Current commands work with PostgreSQL, MySQL, and MariaDB. They will continue to work identically for these databases.

### New Functionality

- SQLite and MongoDB support is additive
- `--service` flag is optional and backward compatible
- Selection prompt only appears when multiple databases exist

### Breaking Changes

**None.** This is a backward-compatible enhancement.

## Future Considerations

- Consider adding `--format` option for MongoDB to support JSON export via mongoexport
- Consider adding compression support for large dumps (gzip)
- Consider adding `--no-confirm` flag for restore in CI/CD scenarios
- Consider streaming progress if database tools provide machine-readable output
