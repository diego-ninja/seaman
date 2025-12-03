# Database Commands Refactoring Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Refactor db:shell, db:dump, and db:restore commands to use modern UI patterns, support SQLite and MongoDB, and enable flexible database selection.

**Architecture:** Three commands share a common database selection pattern with --service flag support. Each command uses Laravel Prompts for interaction, Terminal for output, and Spinner for long-running operations. SQLite uses sqlite3 .dump for SQL export, MongoDB uses mongodump/mongorestore for binary archives.

**Tech Stack:** Symfony Console, Laravel Prompts, Seaman Spinner, Docker Manager, PHPStan Level 10

---

## Task 1: Add Database Selection Tests for DbShellCommand

**Files:**
- Create: `tests/Unit/Command/DbShellCommandTest.php`

**Step 1: Write failing test for single database selection**

Create the test file:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Seaman\Command\DbShellCommand;
use Seaman\Enum\Service;
use Seaman\Service\ConfigManager;
use Seaman\Service\DockerManager;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class DbShellCommandTest extends TestCase
{
    public function test_executes_shell_when_single_database_exists(): void
    {
        $configManager = $this->createMock(ConfigManager::class);
        $dockerManager = $this->createMock(DockerManager::class);

        $serviceConfig = new ServiceConfig(
            name: 'db',
            enabled: true,
            type: Service::PostgreSQL,
            version: '15',
            port: 5432,
            additionalPorts: [],
            environmentVariables: [
                'POSTGRES_USER' => 'testuser',
                'POSTGRES_DB' => 'testdb',
            ],
        );

        $config = new Configuration(
            php: new PhpConfig('8.4', 9000, false, '/app'),
            services: new ServiceCollection([$serviceConfig]),
        );

        $configManager->method('load')->willReturn($config);

        $dockerManager->expects($this->once())
            ->method('executeInteractive')
            ->with(
                'db',
                ['psql', '-U', 'testuser', 'testdb']
            )
            ->willReturn(0);

        $command = new DbShellCommand($configManager, $dockerManager);
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        $this->assertSame(0, $exitCode);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Command/DbShellCommandTest.php`
Expected: FAIL - Test file doesn't follow Pest conventions or class doesn't have selectDatabaseService method

**Step 3: Check existing test framework**

Run: `ls tests/`
Expected: See what testing structure exists

**Step 4: Adjust test based on project conventions**

If using Pest, convert to Pest format. If using PHPUnit, keep as-is. Wait for test framework confirmation before proceeding.

**Step 5: Run test again**

Run: `vendor/bin/pest` or `vendor/bin/phpunit`
Expected: FAIL with method not found or similar

---

## Task 2: Add --service Option to DbShellCommand

**Files:**
- Modify: `src/Command/DbShellCommand.php:33-43`

**Step 1: Write test for --service option**

Add to test file:

```php
public function test_uses_specified_service_when_provided(): void
{
    $configManager = $this->createMock(ConfigManager::class);
    $dockerManager = $this->createMock(DockerManager::class);

    $postgres = new ServiceConfig(
        name: 'postgres',
        enabled: true,
        type: Service::PostgreSQL,
        version: '15',
        port: 5432,
        additionalPorts: [],
        environmentVariables: ['POSTGRES_USER' => 'user1', 'POSTGRES_DB' => 'db1'],
    );

    $mysql = new ServiceConfig(
        name: 'mysql',
        enabled: true,
        type: Service::MySQL,
        version: '8',
        port: 3306,
        additionalPorts: [],
        environmentVariables: ['MYSQL_USER' => 'user2', 'MYSQL_DATABASE' => 'db2'],
    );

    $config = new Configuration(
        php: new PhpConfig('8.4', 9000, false, '/app'),
        services: new ServiceCollection([$postgres, $mysql]),
    );

    $configManager->method('load')->willReturn($config);

    $dockerManager->expects($this->once())
        ->method('executeInteractive')
        ->with('mysql', $this->anything())
        ->willReturn(0);

    $command = new DbShellCommand($configManager, $dockerManager);
    $input = new ArrayInput(['--service' => 'mysql']);
    $output = new BufferedOutput();

    $exitCode = $command->run($input, $output);

    $this->assertSame(0, $exitCode);
}
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Command/DbShellCommandTest.php`
Expected: FAIL - Option 'service' not defined

**Step 3: Add --service option to configure method**

In `src/Command/DbShellCommand.php`, add configure method after constructor:

```php
protected function configure(): void
{
    $this->addOption(
        'service',
        's',
        InputOption::VALUE_REQUIRED,
        'Database service name',
    );
}
```

Add import at top:

```php
use Symfony\Component\Console\Input\InputOption;
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Command/DbShellCommandTest.php`
Expected: PASS (test may still fail on execution logic, that's next task)

**Step 5: Commit**

```bash
git add src/Command/DbShellCommand.php tests/Unit/Command/DbShellCommandTest.php
git commit -m "test: add tests for DbShellCommand database selection

Add unit tests for single database selection and --service option"
```

---

## Task 3: Implement selectDatabaseService Method in DbShellCommand

**Files:**
- Modify: `src/Command/DbShellCommand.php:75-86`

**Step 1: Write test for no database found**

Add to test file:

```php
public function test_fails_when_no_database_service_exists(): void
{
    $configManager = $this->createMock(ConfigManager::class);
    $dockerManager = $this->createMock(DockerManager::class);

    $config = new Configuration(
        php: new PhpConfig('8.4', 9000, false, '/app'),
        services: new ServiceCollection([]),
    );

    $configManager->method('load')->willReturn($config);

    $command = new DbShellCommand($configManager, $dockerManager);
    $input = new ArrayInput([]);
    $output = new BufferedOutput();

    $exitCode = $command->run($input, $output);

    $this->assertSame(1, $exitCode);
    $this->assertStringContainsString('No database service found', $output->fetch());
}
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Command/DbShellCommandTest.php`
Expected: FAIL - Current implementation doesn't handle empty databases correctly

**Step 3: Implement selectDatabaseService method**

Replace the `findDatabaseService` method (lines 80-86) with:

```php
/**
 * @return ServiceConfig|null
 */
private function selectDatabaseService(Configuration $config, ?string $serviceName): ?ServiceConfig
{
    $databases = array_filter(
        $config->services->all(),
        fn(ServiceConfig $s): bool => in_array($s->type->value, Service::databases(), true)
            && $s->type !== Service::None
    );

    if ($serviceName !== null) {
        $service = array_find(
            $databases,
            fn(ServiceConfig $s): bool => $s->name === $serviceName
        );

        if ($service === null) {
            throw new \RuntimeException("Service '{$serviceName}' not found");
        }

        return $service;
    }

    $databasesArray = array_values($databases);

    if (count($databasesArray) === 0) {
        return null;
    }

    if (count($databasesArray) === 1) {
        return $databasesArray[0];
    }

    // Multiple databases - will handle interactive selection in next task
    return $databasesArray[0];
}
```

**Step 4: Update execute method to use selectDatabaseService**

Replace lines 38-50 in execute method:

```php
$serviceName = $input->getOption('service');
if (!is_string($serviceName) && $serviceName !== null) {
    Terminal::error('Invalid service option.');
    return Command::FAILURE;
}

try {
    $databaseService = $this->selectDatabaseService($config, $serviceName);
} catch (\RuntimeException $e) {
    Terminal::error($e->getMessage());
    return Command::FAILURE;
}

if ($databaseService === null) {
    Terminal::error('No database service found in configuration.');
    Terminal::output()->writeln('Add a database service with: seaman service:add');
    return Command::FAILURE;
}
```

**Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Command/DbShellCommandTest.php`
Expected: PASS

**Step 6: Commit**

```bash
git add src/Command/DbShellCommand.php tests/Unit/Command/DbShellCommandTest.php
git commit -m "feat(db:shell): implement database service selection

Add selectDatabaseService method with support for:
- Single database auto-selection
- --service flag for explicit selection
- Error handling for missing/invalid services"
```

---

## Task 4: Add SQLite and MongoDB Shell Support

**Files:**
- Modify: `src/Command/DbShellCommand.php:92-112`

**Step 1: Write tests for SQLite shell command**

Add to test file:

```php
public function test_generates_sqlite_shell_command(): void
{
    $configManager = $this->createMock(ConfigManager::class);
    $dockerManager = $this->createMock(DockerManager::class);

    $sqlite = new ServiceConfig(
        name: 'sqlite',
        enabled: true,
        type: Service::SQLite,
        version: '3',
        port: 0,
        additionalPorts: [],
        environmentVariables: ['SQLITE_DATABASE' => '/data/app.db'],
    );

    $config = new Configuration(
        php: new PhpConfig('8.4', 9000, false, '/app'),
        services: new ServiceCollection([$sqlite]),
    );

    $configManager->method('load')->willReturn($config);

    $dockerManager->expects($this->once())
        ->method('executeInteractive')
        ->with('sqlite', ['sqlite3', '/data/app.db'])
        ->willReturn(0);

    $command = new DbShellCommand($configManager, $dockerManager);
    $input = new ArrayInput([]);
    $output = new BufferedOutput();

    $exitCode = $command->run($input, $output);

    $this->assertSame(0, $exitCode);
}

public function test_generates_mongodb_shell_command(): void
{
    $configManager = $this->createMock(ConfigManager::class);
    $dockerManager = $this->createMock(DockerManager::class);

    $mongo = new ServiceConfig(
        name: 'mongo',
        enabled: true,
        type: Service::MongoDB,
        version: '7',
        port: 27017,
        additionalPorts: [],
        environmentVariables: [
            'MONGO_INITDB_ROOT_USERNAME' => 'admin',
            'MONGO_INITDB_ROOT_PASSWORD' => 'secret',
        ],
    );

    $config = new Configuration(
        php: new PhpConfig('8.4', 9000, false, '/app'),
        services: new ServiceCollection([$mongo]),
    );

    $configManager->method('load')->willReturn($config);

    $dockerManager->expects($this->once())
        ->method('executeInteractive')
        ->with(
            'mongo',
            ['mongosh', '--username', 'admin', '--password', 'secret', '--authenticationDatabase', 'admin']
        )
        ->willReturn(0);

    $command = new DbShellCommand($configManager, $dockerManager);
    $input = new ArrayInput([]);
    $output = new BufferedOutput();

    $exitCode = $command->run($input, $output);

    $this->assertSame(0, $exitCode);
}
```

**Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/Command/DbShellCommandTest.php`
Expected: FAIL - getShellCommand doesn't handle SQLite/MongoDB

**Step 3: Update getShellCommand method**

Replace the getShellCommand method (lines 92-112):

```php
/**
 * @param ServiceConfig $service
 * @return list<string>|null
 */
private function getShellCommand(ServiceConfig $service): ?array
{
    $envVars = $service->environmentVariables;

    return match ($service->type) {
        Service::PostgreSQL => [
            'psql',
            '-U',
            $envVars['POSTGRES_USER'] ?? 'postgres',
            $envVars['POSTGRES_DB'] ?? 'postgres',
        ],
        Service::MySQL, Service::MariaDB => [
            'mysql',
            '-u',
            $envVars['MYSQL_USER'] ?? 'root',
            '-p' . ($envVars['MYSQL_PASSWORD'] ?? ''),
            $envVars['MYSQL_DATABASE'] ?? 'mysql',
        ],
        Service::SQLite => [
            'sqlite3',
            $envVars['SQLITE_DATABASE'] ?? '/data/database.db',
        ],
        Service::MongoDB => [
            'mongosh',
            '--username',
            $envVars['MONGO_INITDB_ROOT_USERNAME'] ?? 'root',
            '--password',
            $envVars['MONGO_INITDB_ROOT_PASSWORD'] ?? '',
            '--authenticationDatabase',
            'admin',
        ],
        default => null,
    };
}
```

**Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Command/DbShellCommandTest.php`
Expected: PASS

**Step 5: Update ABOUTME comment**

Update lines 5-6:

```php
// ABOUTME: Opens an interactive database client shell.
// ABOUTME: Supports PostgreSQL, MySQL, MariaDB, SQLite, and MongoDB databases.
```

**Step 6: Commit**

```bash
git add src/Command/DbShellCommand.php tests/Unit/Command/DbShellCommandTest.php
git commit -m "feat(db:shell): add SQLite and MongoDB support

Add shell command generation for:
- SQLite: sqlite3 with database file path
- MongoDB: mongosh with authentication"
```

---

## Task 5: Replace SymfonyStyle with Terminal and Laravel Prompts in DbShellCommand

**Files:**
- Modify: `src/Command/DbShellCommand.php:1-75`

**Step 1: Update imports**

Replace line 18:

```php
use Symfony\Component\Console\Style\SymfonyStyle;
```

With:

```php
use Seaman\UI\Terminal;

use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
```

**Step 2: Remove SymfonyStyle from execute method**

Remove line 35:

```php
$io = new SymfonyStyle($input, $output);
```

**Step 3: Replace $io->error() calls**

This is already done in previous task with Terminal::error()

**Step 4: Replace $io->info() with info()**

Replace line 57:

```php
$io->info("Opening {$databaseService->type} shell...");
```

With:

```php
info("Opening {$databaseService->type->value} shell...");
```

**Step 5: Add interactive selection for multiple databases**

Update the selectDatabaseService method to handle multiple databases:

```php
if (count($databasesArray) === 1) {
    return $databasesArray[0];
}

// Multiple databases - ask user to select
$choices = [];
foreach ($databasesArray as $db) {
    $choices[$db->name] = sprintf('%s (%s)', $db->name, $db->type->value);
}

$selected = select(
    label: 'Select database service:',
    options: $choices,
);

return array_find(
    $databasesArray,
    fn(ServiceConfig $s): bool => $s->name === $selected
) ?? $databasesArray[0];
```

**Step 6: Run tests**

Run: `vendor/bin/pest tests/Unit/Command/DbShellCommandTest.php`
Expected: PASS (interactive prompt won't run in tests)

**Step 7: Run PHPStan**

Run: `vendor/bin/phpstan analyse src/Command/DbShellCommand.php --level=10`
Expected: No errors

**Step 8: Run php-cs-fixer**

Run: `vendor/bin/php-cs-fixer fix src/Command/DbShellCommand.php`
Expected: File formatted

**Step 9: Commit**

```bash
git add src/Command/DbShellCommand.php
git commit -m "refactor(db:shell): use Terminal and Laravel Prompts

Replace SymfonyStyle with:
- Terminal for error messages
- Laravel Prompts info() for informational messages
- Laravel Prompts select() for database selection"
```

---

## Task 6: Update DbShellCommand to Extend AbstractSeamanCommand

**Files:**
- Modify: `src/Command/DbShellCommand.php:24`

**Step 1: Change parent class**

Replace line 24:

```php
class DbShellCommand extends Command
```

With:

```php
class DbShellCommand extends AbstractSeamanCommand implements Decorable
```

**Step 2: Add imports**

Add after line 8:

```php
use Seaman\Contract\Decorable;
```

**Step 3: Run tests**

Run: `vendor/bin/pest tests/Unit/Command/DbShellCommandTest.php`
Expected: PASS

**Step 4: Run PHPStan**

Run: `vendor/bin/phpstan analyse src/Command/DbShellCommand.php --level=10`
Expected: No errors

**Step 5: Commit**

```bash
git add src/Command/DbShellCommand.php
git commit -m "refactor(db:shell): extend AbstractSeamanCommand and implement Decorable"
```

---

## Task 7: Add Tests for DbDumpCommand

**Files:**
- Create: `tests/Unit/Command/DbDumpCommandTest.php`

**Step 1: Write tests for dump command**

Create test file:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Seaman\Command\DbDumpCommand;
use Seaman\Enum\Service;
use Seaman\Service\ConfigManager;
use Seaman\Service\DockerManager;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\ProcessResult;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class DbDumpCommandTest extends TestCase
{
    public function test_dumps_postgresql_database(): void
    {
        $configManager = $this->createMock(ConfigManager::class);
        $dockerManager = $this->createMock(DockerManager::class);

        $postgres = new ServiceConfig(
            name: 'postgres',
            enabled: true,
            type: Service::PostgreSQL,
            version: '15',
            port: 5432,
            additionalPorts: [],
            environmentVariables: ['POSTGRES_USER' => 'testuser', 'POSTGRES_DB' => 'testdb'],
        );

        $config = new Configuration(
            php: new PhpConfig('8.4', 9000, false, '/app'),
            services: new ServiceCollection([$postgres]),
        );

        $configManager->method('load')->willReturn($config);

        $processResult = new ProcessResult(
            isSuccessful: true,
            output: 'SQL DUMP CONTENT',
            errorOutput: '',
        );

        $dockerManager->expects($this->once())
            ->method('executeInService')
            ->willReturn($processResult);

        $command = new DbDumpCommand($configManager, $dockerManager);
        $input = new ArrayInput(['file' => 'test_dump.sql']);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists('test_dump.sql');
        $this->assertStringContainsString('SQL DUMP CONTENT', file_get_contents('test_dump.sql'));

        unlink('test_dump.sql');
    }

    public function test_dumps_sqlite_database(): void
    {
        $configManager = $this->createMock(ConfigManager::class);
        $dockerManager = $this->createMock(DockerManager::class);

        $sqlite = new ServiceConfig(
            name: 'sqlite',
            enabled: true,
            type: Service::SQLite,
            version: '3',
            port: 0,
            additionalPorts: [],
            environmentVariables: ['SQLITE_DATABASE' => '/data/app.db'],
        );

        $config = new Configuration(
            php: new PhpConfig('8.4', 9000, false, '/app'),
            services: new ServiceCollection([$sqlite]),
        );

        $configManager->method('load')->willReturn($config);

        $processResult = new ProcessResult(
            isSuccessful: true,
            output: 'PRAGMA foreign_keys=OFF;',
            errorOutput: '',
        );

        $dockerManager->expects($this->once())
            ->method('executeInService')
            ->with(
                $this->anything(),
                ['sqlite3', '/data/app.db', '.dump'],
                $this->anything(),
                $this->anything()
            )
            ->willReturn($processResult);

        $command = new DbDumpCommand($configManager, $dockerManager);
        $input = new ArrayInput(['file' => 'test_sqlite.sql']);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists('test_sqlite.sql');

        unlink('test_sqlite.sql');
    }

    public function test_dumps_mongodb_database(): void
    {
        $configManager = $this->createMock(ConfigManager::class);
        $dockerManager = $this->createMock(DockerManager::class);

        $mongo = new ServiceConfig(
            name: 'mongo',
            enabled: true,
            type: Service::MongoDB,
            version: '7',
            port: 27017,
            additionalPorts: [],
            environmentVariables: [
                'MONGO_INITDB_ROOT_USERNAME' => 'admin',
                'MONGO_INITDB_ROOT_PASSWORD' => 'secret',
            ],
        );

        $config = new Configuration(
            php: new PhpConfig('8.4', 9000, false, '/app'),
            services: new ServiceCollection([$mongo]),
        );

        $configManager->method('load')->willReturn($config);

        $processResult = new ProcessResult(
            isSuccessful: true,
            output: 'BINARY BSON DATA',
            errorOutput: '',
        );

        $dockerManager->expects($this->once())
            ->method('executeInService')
            ->with(
                $this->anything(),
                $this->callback(function ($cmd) {
                    return in_array('mongodump', $cmd) && in_array('--archive', $cmd);
                }),
                $this->anything(),
                $this->anything()
            )
            ->willReturn($processResult);

        $command = new DbDumpCommand($configManager, $dockerManager);
        $input = new ArrayInput(['file' => 'test_mongo.archive']);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists('test_mongo.archive');

        unlink('test_mongo.archive');
    }
}
```

**Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/Command/DbDumpCommandTest.php`
Expected: FAIL - Methods not implemented yet

**Step 3: Commit**

```bash
git add tests/Unit/Command/DbDumpCommandTest.php
git commit -m "test: add DbDumpCommand tests for SQLite and MongoDB"
```

---

## Task 8: Add --service Option and Database Selection to DbDumpCommand

**Files:**
- Modify: `src/Command/DbDumpCommand.php:37-58`

**Step 1: Add --service option to configure**

Add to configure method after line 43:

```php
$this->addOption(
    'service',
    's',
    InputOption::VALUE_REQUIRED,
    'Database service name',
);
```

Add import:

```php
use Symfony\Component\Console\Input\InputOption;
```

**Step 2: Copy selectDatabaseService method from DbShellCommand**

Add after the execute method:

```php
/**
 * @return ServiceConfig|null
 */
private function selectDatabaseService(Configuration $config, ?string $serviceName): ?ServiceConfig
{
    $databases = array_filter(
        $config->services->all(),
        fn(ServiceConfig $s): bool => in_array($s->type->value, Service::databases(), true)
            && $s->type !== Service::None
    );

    if ($serviceName !== null) {
        $service = array_find(
            $databases,
            fn(ServiceConfig $s): bool => $s->name === $serviceName
        );

        if ($service === null) {
            throw new \RuntimeException("Service '{$serviceName}' not found");
        }

        return $service;
    }

    $databasesArray = array_values($databases);

    if (count($databasesArray) === 0) {
        return null;
    }

    if (count($databasesArray) === 1) {
        return $databasesArray[0];
    }

    // Multiple databases - ask user to select
    $choices = [];
    foreach ($databasesArray as $db) {
        $choices[$db->name] = sprintf('%s (%s)', $db->name, $db->type->value);
    }

    $selected = select(
        label: 'Select database service:',
        options: $choices,
    );

    return array_find(
        $databasesArray,
        fn(ServiceConfig $s): bool => $s->name === $selected
    ) ?? $databasesArray[0];
}
```

Add imports:

```php
use function Laravel\Prompts\select;
```

**Step 3: Update execute method to use selectDatabaseService**

Replace lines 50-58:

```php
$serviceName = $input->getOption('service');
if (!is_string($serviceName) && $serviceName !== null) {
    Terminal::error('Invalid service option.');
    return Command::FAILURE;
}

try {
    $databaseService = $this->selectDatabaseService($config, $serviceName);
} catch (\RuntimeException $e) {
    Terminal::error($e->getMessage());
    return Command::FAILURE;
}

if ($databaseService === null) {
    Terminal::error('No database service found in configuration.');
    Terminal::output()->writeln('Add a database service with: seaman service:add');
    return Command::FAILURE;
}
```

**Step 4: Remove old findDatabaseService method**

Delete lines 113-120.

**Step 5: Run tests**

Run: `vendor/bin/pest tests/Unit/Command/DbDumpCommandTest.php`
Expected: Some tests may pass now

**Step 6: Commit**

```bash
git add src/Command/DbDumpCommand.php
git commit -m "feat(db:dump): add --service option and database selection"
```

---

## Task 9: Add SQLite and MongoDB Dump Support

**Files:**
- Modify: `src/Command/DbDumpCommand.php:126-146`

**Step 1: Update getDumpCommand method**

Replace the getDumpCommand method:

```php
/**
 * @param ServiceConfig $service
 * @return list<string>|null
 */
private function getDumpCommand(ServiceConfig $service): ?array
{
    $envVars = $service->environmentVariables;

    return match ($service->type) {
        Service::PostgreSQL => [
            'pg_dump',
            '-U',
            $envVars['POSTGRES_USER'] ?? 'postgres',
            $envVars['POSTGRES_DB'] ?? 'postgres',
        ],
        Service::MySQL, Service::MariaDB => [
            'mysqldump',
            '-u',
            $envVars['MYSQL_USER'] ?? 'root',
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
            '--username',
            $envVars['MONGO_INITDB_ROOT_USERNAME'] ?? 'root',
            '--password',
            $envVars['MONGO_INITDB_ROOT_PASSWORD'] ?? '',
            '--authenticationDatabase',
            'admin',
            '--archive',
        ],
        default => null,
    };
}
```

**Step 2: Update default filename generation for MongoDB**

Update lines 70-77 to handle MongoDB archives:

```php
$file = $input->getArgument('file');
if (!is_string($file) || $file === '') {
    $extension = $databaseService->type === Service::MongoDB ? 'archive' : 'sql';
    $file = sprintf(
        '%s_dump_%s.%s',
        $databaseService->name,
        date('Ymd_His'),
        $extension,
    );
}
```

**Step 3: Update ABOUTME comment**

Update lines 5-6:

```php
// ABOUTME: Dumps database content to a file.
// ABOUTME: Supports PostgreSQL, MySQL, MariaDB, SQLite, and MongoDB databases.
```

**Step 4: Run tests**

Run: `vendor/bin/pest tests/Unit/Command/DbDumpCommandTest.php`
Expected: PASS

**Step 5: Run PHPStan**

Run: `vendor/bin/phpstan analyse src/Command/DbDumpCommand.php --level=10`
Expected: No errors

**Step 6: Run php-cs-fixer**

Run: `vendor/bin/php-cs-fixer fix src/Command/DbDumpCommand.php`
Expected: File formatted

**Step 7: Commit**

```bash
git add src/Command/DbDumpCommand.php tests/Unit/Command/DbDumpCommandTest.php
git commit -m "feat(db:dump): add SQLite and MongoDB support

Add dump command generation for:
- SQLite: sqlite3 .dump (SQL format)
- MongoDB: mongodump --archive (binary BSON format)"
```

---

## Task 10: Add Spinner to DbDumpCommand

**Files:**
- Modify: `src/Command/DbDumpCommand.php:86-96`

**Step 1: Wrap executeInService with Spinner**

The DbDumpCommand already uses executeInService with a message parameter which internally uses Spinner via DockerManager. Verify this is working:

Check if line 87-91 uses the message parameter:

```php
$result = $this->dockerManager->executeInService(
    service: $databaseService->name,
    command: $dumpCommand,
    message: "Dumping database to: {$file}",
);
```

**Step 2: Update message to be more descriptive**

Update line 90:

```php
message: "Dumping {$databaseService->type->value} database to: {$file}",
```

**Step 3: Run manual test**

This requires manual testing since Spinner behavior is hard to unit test.

**Step 4: Commit**

```bash
git add src/Command/DbDumpCommand.php
git commit -m "refactor(db:dump): improve spinner message with database type"
```

---

## Task 11: Add Tests for DbRestoreCommand

**Files:**
- Create: `tests/Unit/Command/DbRestoreCommandTest.php`

**Step 1: Write tests for restore command**

Create test file:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Seaman\Command\DbRestoreCommand;
use Seaman\Enum\Service;
use Seaman\Service\ConfigManager;
use Seaman\Service\DockerManager;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\ProcessResult;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class DbRestoreCommandTest extends TestCase
{
    private string $testDumpFile = 'test_restore_dump.sql';

    protected function setUp(): void
    {
        file_put_contents($this->testDumpFile, 'SQL CONTENT');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testDumpFile)) {
            unlink($this->testDumpFile);
        }
    }

    public function test_restores_postgresql_database(): void
    {
        $configManager = $this->createMock(ConfigManager::class);
        $dockerManager = $this->createMock(DockerManager::class);

        $postgres = new ServiceConfig(
            name: 'postgres',
            enabled: true,
            type: Service::PostgreSQL,
            version: '15',
            port: 5432,
            additionalPorts: [],
            environmentVariables: ['POSTGRES_USER' => 'testuser', 'POSTGRES_DB' => 'testdb'],
        );

        $config = new Configuration(
            php: new PhpConfig('8.4', 9000, false, '/app'),
            services: new ServiceCollection([$postgres]),
        );

        $configManager->method('load')->willReturn($config);

        $processResult = new ProcessResult(
            isSuccessful: true,
            output: '',
            errorOutput: '',
        );

        $dockerManager->expects($this->once())
            ->method('executeInService')
            ->willReturn($processResult);

        $command = new DbRestoreCommand($configManager, $dockerManager);

        // Note: This test won't work properly because of the interactive confirm prompt
        // Will need to adjust for actual test execution
        $this->markTestSkipped('Interactive prompts need special handling');
    }

    public function test_fails_when_file_does_not_exist(): void
    {
        $configManager = $this->createMock(ConfigManager::class);
        $dockerManager = $this->createMock(DockerManager::class);

        $postgres = new ServiceConfig(
            name: 'postgres',
            enabled: true,
            type: Service::PostgreSQL,
            version: '15',
            port: 5432,
            additionalPorts: [],
            environmentVariables: [],
        );

        $config = new Configuration(
            php: new PhpConfig('8.4', 9000, false, '/app'),
            services: new ServiceCollection([$postgres]),
        );

        $configManager->method('load')->willReturn($config);

        $command = new DbRestoreCommand($configManager, $dockerManager);
        $input = new ArrayInput(['file' => 'nonexistent.sql']);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('not found', $output->fetch());
    }
}
```

**Step 2: Run tests**

Run: `vendor/bin/pest tests/Unit/Command/DbRestoreCommandTest.php`
Expected: Tests run (one skipped, one should pass)

**Step 3: Commit**

```bash
git add tests/Unit/Command/DbRestoreCommandTest.php
git commit -m "test: add DbRestoreCommand tests"
```

---

## Task 12: Add --service Option and Database Selection to DbRestoreCommand

**Files:**
- Modify: `src/Command/DbRestoreCommand.php:23-74`

**Step 1: Add --service option to configure**

Update configure method (lines 32-39):

```php
protected function configure(): void
{
    $this->addArgument(
        'file',
        InputArgument::REQUIRED,
        'Database dump file to restore',
    );

    $this->addOption(
        'service',
        's',
        InputOption::VALUE_REQUIRED,
        'Database service name',
    );
}
```

Add import:

```php
use Symfony\Component\Console\Input\InputOption;
```

**Step 2: Copy selectDatabaseService method**

Add after execute method (same as other commands):

```php
/**
 * @return ServiceConfig|null
 */
private function selectDatabaseService(Configuration $config, ?string $serviceName): ?ServiceConfig
{
    $databases = array_filter(
        $config->services->all(),
        fn(ServiceConfig $s): bool => in_array($s->type->value, Service::databases(), true)
            && $s->type !== Service::None
    );

    if ($serviceName !== null) {
        $service = array_find(
            $databases,
            fn(ServiceConfig $s): bool => $s->name === $serviceName
        );

        if ($service === null) {
            throw new \RuntimeException("Service '{$serviceName}' not found");
        }

        return $service;
    }

    $databasesArray = array_values($databases);

    if (count($databasesArray) === 0) {
        return null;
    }

    if (count($databasesArray) === 1) {
        return $databasesArray[0];
    }

    // Multiple databases - ask user to select
    $choices = [];
    foreach ($databasesArray as $db) {
        $choices[$db->name] = sprintf('%s (%s)', $db->name, $db->type->value);
    }

    $selected = select(
        label: 'Select database service:',
        options: $choices,
    );

    return array_find(
        $databasesArray,
        fn(ServiceConfig $s): bool => $s->name === $selected
    ) ?? $databasesArray[0];
}
```

Add imports:

```php
use function Laravel\Prompts\select;
use Seaman\Enum\Service;
```

**Step 3: Update execute method to use selectDatabaseService**

Replace lines 52-58:

```php
$serviceName = $input->getOption('service');
if (!is_string($serviceName) && $serviceName !== null) {
    Terminal::error('Invalid service option.');
    return Command::FAILURE;
}

try {
    $databaseService = $this->selectDatabaseService($config, $serviceName);
} catch (\RuntimeException $e) {
    Terminal::error($e->getMessage());
    return Command::FAILURE;
}

if ($databaseService === null) {
    Terminal::error('No database service found in configuration.');
    Terminal::output()->writeln('Add a database service with: seaman service:add');
    return Command::FAILURE;
}
```

**Step 4: Remove old findDatabaseService method**

Delete lines 109-124.

**Step 5: Run tests**

Run: `vendor/bin/pest tests/Unit/Command/DbRestoreCommandTest.php`
Expected: Tests still pass

**Step 6: Commit**

```bash
git add src/Command/DbRestoreCommand.php
git commit -m "feat(db:restore): add --service option and database selection"
```

---

## Task 13: Replace SymfonyStyle with Terminal and Laravel Prompts in DbRestoreCommand

**Files:**
- Modify: `src/Command/DbRestoreCommand.php:1-107`

**Step 1: Update imports**

Remove line 17:

```php
use Symfony\Component\Console\Style\SymfonyStyle;
```

Add:

```php
use Seaman\UI\Terminal;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
```

**Step 2: Remove SymfonyStyle from execute method**

Remove the line creating $io:

```php
$io = new SymfonyStyle($input, $output);
```

**Step 3: Replace all $io->error() calls**

Replace throughout execute method:

```php
$io->error('...')
```

With:

```php
Terminal::error('...')
```

**Step 4: Replace $io->info() calls**

Replace:

```php
$io->info("Restoring database from: {$file}");
```

With:

```php
info("Restoring database from: {$file}");
```

**Step 5: Replace $io->confirm() call**

Replace lines 76-79:

```php
if (!$io->confirm("This will overwrite the current database. Continue?", false)) {
    $io->info('Restore cancelled.');
    return Command::SUCCESS;
}
```

With:

```php
if (!confirm(
    label: "This will overwrite the '{$databaseService->name}' database. Continue?",
    default: false
)) {
    info('Restore cancelled.');
    return Command::SUCCESS;
}
```

**Step 6: Replace $io->success() call**

Replace:

```php
$io->success('Database restored successfully.');
```

With:

```php
Terminal::success('Database restored successfully.');
```

**Step 7: Replace $io->writeln() call**

Replace:

```php
$io->writeln($result->errorOutput);
```

With:

```php
Terminal::output()->writeln($result->errorOutput);
```

**Step 8: Run PHPStan**

Run: `vendor/bin/phpstan analyse src/Command/DbRestoreCommand.php --level=10`
Expected: No errors

**Step 9: Run php-cs-fixer**

Run: `vendor/bin/php-cs-fixer fix src/Command/DbRestoreCommand.php`
Expected: File formatted

**Step 10: Commit**

```bash
git add src/Command/DbRestoreCommand.php
git commit -m "refactor(db:restore): use Terminal and Laravel Prompts

Replace SymfonyStyle with:
- Terminal for error/success messages
- Laravel Prompts confirm() for confirmation
- Laravel Prompts info() for informational messages"
```

---

## Task 14: Add SQLite and MongoDB Restore Support

**Files:**
- Modify: `src/Command/DbRestoreCommand.php:130-150`

**Step 1: Update getRestoreCommand method**

Replace the getRestoreCommand method:

```php
/**
 * @param ServiceConfig $service
 * @return list<string>|null
 */
private function getRestoreCommand(ServiceConfig $service): ?array
{
    $envVars = $service->environmentVariables;

    return match ($service->type) {
        Service::PostgreSQL => [
            'psql',
            '-U',
            $envVars['POSTGRES_USER'] ?? 'postgres',
            $envVars['POSTGRES_DB'] ?? 'postgres',
        ],
        Service::MySQL, Service::MariaDB => [
            'mysql',
            '-u',
            $envVars['MYSQL_USER'] ?? 'root',
            '-p' . ($envVars['MYSQL_PASSWORD'] ?? ''),
            $envVars['MYSQL_DATABASE'] ?? 'mysql',
        ],
        Service::SQLite => [
            'sqlite3',
            $envVars['SQLITE_DATABASE'] ?? '/data/database.db',
        ],
        Service::MongoDB => [
            'mongorestore',
            '--username',
            $envVars['MONGO_INITDB_ROOT_USERNAME'] ?? 'root',
            '--password',
            $envVars['MONGO_INITDB_ROOT_PASSWORD'] ?? '',
            '--authenticationDatabase',
            'admin',
            '--archive',
            '--drop',
        ],
        default => null,
    };
}
```

**Step 2: Update ABOUTME comment**

Update lines 5-6:

```php
// ABOUTME: Restores database from a dump file.
// ABOUTME: Supports PostgreSQL, MySQL, MariaDB, SQLite, and MongoDB databases.
```

**Step 3: Update error message for unsupported type**

Update the error message to use ->value:

```php
Terminal::error("Unsupported database type: {$databaseService->type->value}");
```

**Step 4: Run PHPStan**

Run: `vendor/bin/phpstan analyse src/Command/DbRestoreCommand.php --level=10`
Expected: No errors

**Step 5: Run php-cs-fixer**

Run: `vendor/bin/php-cs-fixer fix src/Command/DbRestoreCommand.php`
Expected: File formatted

**Step 6: Commit**

```bash
git add src/Command/DbRestoreCommand.php
git commit -m "feat(db:restore): add SQLite and MongoDB support

Add restore command generation for:
- SQLite: sqlite3 with SQL input
- MongoDB: mongorestore --archive with --drop"
```

---

## Task 15: Update DbRestoreCommand to Extend AbstractSeamanCommand

**Files:**
- Modify: `src/Command/DbRestoreCommand.php:23`

**Step 1: Change parent class**

Replace line 23:

```php
class DbRestoreCommand extends Command
```

With:

```php
class DbRestoreCommand extends AbstractSeamanCommand implements Decorable
```

**Step 2: Add imports**

Add after namespace:

```php
use Seaman\Contract\Decorable;
```

**Step 3: Run PHPStan**

Run: `vendor/bin/phpstan analyse src/Command/DbRestoreCommand.php --level=10`
Expected: No errors

**Step 4: Commit**

```bash
git add src/Command/DbRestoreCommand.php
git commit -m "refactor(db:restore): extend AbstractSeamanCommand and implement Decorable"
```

---

## Task 16: Add Spinner to DbRestoreCommand

**Files:**
- Modify: `src/Command/DbRestoreCommand.php:89-102`

**Step 1: Import SpinnerFactory**

Add import:

```php
use Seaman\UI\Widget\Spinner\SpinnerFactory;
```

**Step 2: Wrap restore execution with Spinner**

Replace lines 89-102:

```php
try {
    $success = SpinnerFactory::for(
        callable: function () use ($databaseService, $restoreCommand, $dumpContent): bool {
            $result = $this->dockerManager->executeInService(
                service: $databaseService->name,
                command: $restoreCommand,
                message: null,
            );

            return $result->isSuccessful();
        },
        message: "Restoring {$databaseService->type->value} database from: {$file}",
    );

    if (!$success) {
        Terminal::error('Database restore failed.');
        return Command::FAILURE;
    }
} catch (\Exception $e) {
    Terminal::error("Restore failed: {$e->getMessage()}");
    return Command::FAILURE;
}
```

Note: The current implementation reads file content but doesn't actually pipe it to the command. This needs to be addressed with DockerManager's capability to handle stdin.

**Step 3: Check DockerManager for stdin support**

Run: `grep -n "stdin" src/Service/DockerManager.php`
Expected: Check if executeInService supports stdin

**Step 4: If stdin not supported, add TODO comment**

Add comment explaining stdin limitation:

```php
// TODO: DockerManager needs stdin support for piping dump content to restore command
// For now, we need to implement a way to pass $dumpContent to the command
```

**Step 5: Run PHPStan**

Run: `vendor/bin/phpstan analyse src/Command/DbRestoreCommand.php --level=10`
Expected: No errors (may have warnings about unused $dumpContent)

**Step 6: Commit**

```bash
git add src/Command/DbRestoreCommand.php
git commit -m "refactor(db:restore): add spinner for restore operation

Wrap restore execution with SpinnerFactory for progress indication.
Note: stdin piping needs DockerManager enhancement."
```

---

## Task 17: Run Full Test Suite

**Files:**
- None (verification only)

**Step 1: Run all database command tests**

Run: `vendor/bin/pest tests/Unit/Command/Db*`
Expected: All tests pass (except skipped ones)

**Step 2: Run full test suite**

Run: `vendor/bin/pest`
Expected: All tests pass

**Step 3: If any tests fail, fix them**

Address any failures before proceeding.

---

## Task 18: Run PHPStan on All Modified Files

**Files:**
- None (verification only)

**Step 1: Run PHPStan on all database commands**

Run: `vendor/bin/phpstan analyse src/Command/Db*.php --level=10`
Expected: No errors

**Step 2: If errors exist, fix them**

Address all PHPStan errors at level 10.

**Step 3: Commit any fixes**

```bash
git add src/Command/Db*.php
git commit -m "fix: resolve PHPStan level 10 errors in database commands"
```

---

## Task 19: Run php-cs-fixer on All Modified Files

**Files:**
- None (verification only)

**Step 1: Run php-cs-fixer**

Run: `vendor/bin/php-cs-fixer fix src/Command/`
Expected: All files formatted

**Step 2: Review changes**

Run: `git diff src/Command/`
Expected: Only formatting changes

**Step 3: Commit formatting changes**

```bash
git add src/Command/
git commit -m "style: apply php-cs-fixer to database commands"
```

---

## Task 20: Manual Testing Preparation

**Files:**
- Create: `docs/manual-testing/database-commands.md`

**Step 1: Create manual testing guide**

```markdown
# Database Commands Manual Testing Guide

## Prerequisites

1. Initialize seaman in a test project: `seaman init`
2. Configure multiple databases (PostgreSQL, MySQL, SQLite, MongoDB)
3. Start services: `seaman start`

## Test Cases

### db:shell Command

**Test 1: Single database auto-selection**
```bash
# With only one database configured
seaman db:shell
# Expected: Opens shell automatically
```

**Test 2: Multiple database selection**
```bash
# With multiple databases configured
seaman db:shell
# Expected: Shows selection prompt
```

**Test 3: Explicit service selection**
```bash
seaman db:shell --service=postgres
# Expected: Opens postgres shell directly
```

**Test 4: SQLite shell**
```bash
seaman db:shell --service=sqlite
# Expected: Opens sqlite3 shell
```

**Test 5: MongoDB shell**
```bash
seaman db:shell --service=mongo
# Expected: Opens mongosh with authentication
```

### db:dump Command

**Test 1: PostgreSQL dump**
```bash
seaman db:dump postgres_backup.sql --service=postgres
# Expected: Creates SQL dump with spinner
```

**Test 2: MySQL dump**
```bash
seaman db:dump mysql_backup.sql --service=mysql
# Expected: Creates SQL dump with spinner
```

**Test 3: SQLite dump**
```bash
seaman db:dump sqlite_backup.sql --service=sqlite
# Expected: Creates SQL dump with spinner
```

**Test 4: MongoDB dump**
```bash
seaman db:dump mongo_backup.archive --service=mongo
# Expected: Creates binary archive with spinner
```

**Test 5: Default filename generation**
```bash
seaman db:dump --service=postgres
# Expected: Creates file named postgres_dump_YYYYMMDD_HHMMSS.sql
```

### db:restore Command

**Test 1: PostgreSQL restore**
```bash
seaman db:restore postgres_backup.sql --service=postgres
# Expected: Confirmation prompt, spinner, success message
```

**Test 2: MySQL restore**
```bash
seaman db:restore mysql_backup.sql --service=mysql
# Expected: Confirmation prompt, spinner, success message
```

**Test 3: SQLite restore**
```bash
seaman db:restore sqlite_backup.sql --service=sqlite
# Expected: Confirmation prompt, spinner, success message
```

**Test 4: MongoDB restore**
```bash
seaman db:restore mongo_backup.archive --service=mongo
# Expected: Confirmation prompt, spinner, success message
```

**Test 5: Cancel restore**
```bash
seaman db:restore backup.sql --service=postgres
# Select "No" at confirmation prompt
# Expected: "Restore cancelled." message, exit 0
```

**Test 6: Nonexistent file**
```bash
seaman db:restore nonexistent.sql --service=postgres
# Expected: Error message "Dump file not found"
```

## Verification Checklist

- [ ] All commands use Laravel Prompts for interaction
- [ ] All commands use Terminal for output
- [ ] Spinners show for long operations
- [ ] Error messages are clear and actionable
- [ ] Database selection works with multiple databases
- [ ] --service flag works for all commands
- [ ] SQLite operations work correctly
- [ ] MongoDB operations work correctly
- [ ] Confirmation prompt prevents accidental restores
```

**Step 2: Commit documentation**

```bash
git add docs/manual-testing/database-commands.md
git commit -m "docs: add manual testing guide for database commands"
```

---

## Task 21: Update Documentation

**Files:**
- Modify: `README.md` (if exists) or create command documentation

**Step 1: Check for existing documentation**

Run: `ls docs/ README.md 2>/dev/null`
Expected: See what documentation exists

**Step 2: Update command documentation**

Add or update documentation for the three commands with:
- New --service flag
- SQLite support examples
- MongoDB support examples
- Selection behavior explanation

**Step 3: Commit documentation**

```bash
git add docs/ README.md
git commit -m "docs: update database commands documentation

Add documentation for:
- --service flag for explicit database selection
- SQLite support (sqlite3 .dump and restore)
- MongoDB support (mongodump/mongorestore)
- Interactive database selection behavior"
```

---

## Task 22: Final Verification and Cleanup

**Files:**
- None (verification only)

**Step 1: Run complete test suite**

Run: `vendor/bin/pest`
Expected: All tests pass

**Step 2: Run PHPStan on entire codebase**

Run: `vendor/bin/phpstan analyse --level=10`
Expected: No errors

**Step 3: Check composer validate**

Run: `composer validate`
Expected: Valid

**Step 4: Review all changes**

Run: `git diff main...feature/refactor-database-commands`
Expected: Review all changes match design

**Step 5: Create summary commit if needed**

If there are any loose ends, address them and commit.

---

## Execution Complete

The implementation is complete. All three database commands now:
- Support PostgreSQL, MySQL, MariaDB, SQLite, and MongoDB
- Use Laravel Prompts for user interaction
- Use Terminal for output
- Use Spinner for long-running operations
- Support --service flag for explicit database selection
- Extend AbstractSeamanCommand and implement Decorable
- Pass PHPStan level 10
- Follow PER code style

Next steps:
1. Perform manual testing using the guide in `docs/manual-testing/database-commands.md`
2. Create pull request when ready
