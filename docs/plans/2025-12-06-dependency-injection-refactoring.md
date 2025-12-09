# Dependency Injection Refactoring

## Overview

Introduce PHP-DI container to eliminate duplicated instantiation patterns and improve testability across the codebase.

## Goals

1. Inject `DockerManager` instead of instantiating in 12+ locations
2. Inject `ConfigManager` instead of instantiating in 9+ locations
3. Consolidate 3 identical Execute commands into one parametrized class
4. Improve testability by making dependencies explicit

## Dependencies

- `php-di/php-di` - Lightweight DI container with autowiring

## New Files

### config/container.php

Central container configuration defining:

- `projectRoot` - String parameter from `getcwd()`
- `ServiceRegistry` - Singleton via `ServiceRegistry::create()`
- `ConfigurationValidator` - Simple instance
- `DockerManager` - Depends on projectRoot
- `ConfigManager` - Depends on projectRoot, ServiceRegistry, ConfigurationValidator
- `ExecuteCommand` variants - 3 registrations with different prefixes

## Modified Files

### src/Application.php

- Add `buildContainer()` method to create PHP-DI container
- Replace manual command instantiation with container resolution
- Container built once at startup

### New: src/Command/ExecuteCommand.php

Unified command replacing:
- `ExecuteComposerCommand.php` (deleted)
- `ExecuteConsoleCommand.php` (deleted)
- `ExecutePhpCommand.php` (deleted)

Constructor receives:
- `string $name` - Command name (exec:composer, exec:console, exec:php)
- `string $description` - Command description
- `array $aliases` - Command aliases
- `array $commandPrefix` - Prefix to prepend to args (['composer'], ['php', 'bin/console'], ['php'])
- `DockerManager $dockerManager` - Injected dependency

### Commands with DockerManager Injection

Add `DockerManager` constructor parameter, remove internal instantiation:

- `src/Command/StopCommand.php`
- `src/Command/RestartCommand.php`
- `src/Command/StartCommand.php`
- `src/Command/StatusCommand.php`
- `src/Command/ShellCommand.php`
- `src/Command/LogsCommand.php`
- `src/Command/DestroyCommand.php`
- `src/Command/RebuildCommand.php`
- `src/Command/XdebugCommand.php`
- `src/Command/AbstractServiceCommand.php`

### Commands with ConfigManager Injection

Add `ConfigManager` constructor parameter, remove ConfigurationValidator + ConfigManager instantiation:

- `src/Command/ProxyEnableCommand.php`
- `src/Command/ProxyDisableCommand.php`
- `src/Command/ProxyConfigureDnsCommand.php`
- `src/Command/DevContainerGenerateCommand.php`

### Trait: src/Command/Concern/ExecutesInContainer.php

Change `executeInContainer()` to receive `DockerManager` as parameter instead of creating internally.

## Container Registration Pattern

```php
// Autowired (PHP-DI resolves dependencies automatically)
StopCommand::class => DI\autowire(),

// Manual registration for parametrized commands
ExecuteCommand::class . '.composer' => DI\create(ExecuteCommand::class)
    ->constructor(
        'exec:composer',
        'Run composer commands on application container',
        ['composer'],
        ['composer'],
        DI\get(DockerManager::class),
    ),
```

## Deleted Files

- `src/Command/ExecuteComposerCommand.php`
- `src/Command/ExecuteConsoleCommand.php`
- `src/Command/ExecutePhpCommand.php`

## Implementation Order

1. Install PHP-DI dependency
2. Create `config/container.php` with core services
3. Create unified `ExecuteCommand` class
4. Update `Application.php` to use container
5. Inject `DockerManager` into commands (12 files)
6. Inject `ConfigManager` into commands (4 files)
7. Update `ExecutesInContainer` trait
8. Delete old Execute command files
9. Run PHPStan and fix issues
10. Run tests

## Testing Considerations

With DI, commands become easily testable:

```php
$mockDocker = $this->createMock(DockerManager::class);
$command = new StopCommand($mockDocker);
// Test command behavior with controlled mock
```

## Estimated Impact

- ~150 lines of duplicated instantiation code removed
- 3 command files consolidated into 1
- All dependencies explicit and injectable
- Improved test isolation capability
