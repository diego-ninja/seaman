# Event System Design

**Date:** 2025-11-29
**Status:** Validated
**Author:** Diego & Claude

## Overview

Implement a Symfony EventDispatcher-based event system for Seaman using PHP 8 attributes for auto-discovery of event listeners. The system will use native Symfony Console events with auto-registration of listeners in development mode and precompiled listener list in PHAR mode.

## Goals

1. Use Symfony EventDispatcher for standard Console events
2. Auto-discover listeners using PHP 8 attributes
3. Support priority-based listener execution
4. Optimize for PHAR with precompiled listener list
5. Keep development experience simple (no manual registration)

## Architecture

### Components

1. **`AsEventListener` Attribute** - Marks classes as event listeners
   - Placed at class level
   - Parameters: `event` (string), `priority` (int, default: 0)
   - PHP 8 native attribute

2. **`ListenerDiscovery`** - Discovers listeners in `src/Listener/`
   - Scans PHP files in listener directory
   - Reads attributes using Reflection API
   - Returns metadata for listener registration

3. **`Application` (modified)** - Configures EventDispatcher
   - Development: uses ListenerDiscovery to scan listeners
   - PHAR: loads precompiled array from `config/listeners.php`
   - Registers all discovered listeners with priorities

4. **Concrete Listeners** - Implement hook logic
   - One class = one event
   - Must have `__invoke(ConsoleEvent $event): void` method
   - Examples: LogCommandListener, NotifyOnErrorListener

### Execution Flow

1. **Application Bootstrap:**
   - Detects execution mode (PHAR vs development)
   - Obtains listener list (discovery or cached)
   - Creates EventDispatcher
   - Registers listeners with their priorities

2. **Command Execution:**
   - Dispatcher triggers listeners for each event
   - Listeners execute in priority order (high to low)
   - Each listener receives the event object

3. **PHAR Build:**
   - BuildCommand scans listeners during build
   - Generates `config/listeners.php` with metadata
   - PHAR includes precompiled listener list

## File Structure

```
src/
├── Attribute/
│   └── AsEventListener.php          # PHP 8 attribute
├── EventListener/
│   ├── ListenerDiscovery.php        # Listener scanner
│   └── EventListenerMetadata.php    # Value object
├── Listener/                        # Listeners go here
│   ├── LogCommandListener.php
│   └── NotifyOnErrorListener.php
└── Application.php                  # Modified

config/
└── listeners.php                    # Generated for PHAR
```

## Component Details

### 1. AsEventListener Attribute

```php
<?php

// ABOUTME: Attribute to mark event listener classes.
// ABOUTME: Specifies event name and execution priority.

declare(strict_types=1);

namespace Seaman\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsEventListener
{
    /**
     * @param string $event Event name (e.g., ConsoleEvents::COMMAND)
     * @param int $priority Execution priority (higher = earlier, default: 0)
     */
    public function __construct(
        public string $event,
        public int $priority = 0,
    ) {
    }
}
```

**Usage:**
```php
#[AsEventListener(event: ConsoleEvents::COMMAND, priority: 100)]
class MyListener
{
    public function __invoke(ConsoleCommandEvent $event): void
    {
        // Handle event
    }
}
```

### 2. EventListenerMetadata Value Object

```php
<?php

// ABOUTME: Value object containing event listener metadata.
// ABOUTME: Used for registering listeners with EventDispatcher.

declare(strict_types=1);

namespace Seaman\EventListener;

final readonly class EventListenerMetadata
{
    /**
     * @param string $className Fully qualified class name
     * @param string $event Event name
     * @param int $priority Execution priority
     */
    public function __construct(
        public string $className,
        public string $event,
        public int $priority,
    ) {
    }
}
```

### 3. ListenerDiscovery

```php
<?php

// ABOUTME: Discovers event listeners using reflection and attributes.
// ABOUTME: Scans listener directory for classes with AsEventListener attribute.

declare(strict_types=1);

namespace Seaman\EventListener;

use Seaman\Attribute\AsEventListener;
use ReflectionClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final readonly class ListenerDiscovery
{
    public function __construct(
        private string $listenerDir,
    ) {
    }

    /**
     * Discover all event listeners in the listener directory.
     *
     * @return list<EventListenerMetadata>
     */
    public function discover(): array
    {
        $listeners = [];

        foreach ($this->scanDirectory() as $className) {
            $metadata = $this->extractMetadata($className);
            if ($metadata !== null) {
                $listeners[] = $metadata;
            }
        }

        // Sort by priority (descending)
        usort($listeners, fn($a, $b) => $b->priority <=> $a->priority);

        return $listeners;
    }

    /**
     * Scan directory for PHP classes.
     *
     * @return list<string> Fully qualified class names
     */
    private function scanDirectory(): array
    {
        $classes = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->listenerDir)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $className = $this->getClassNameFromFile($file->getPathname());
                if ($className !== null) {
                    $classes[] = $className;
                }
            }
        }

        return $classes;
    }

    /**
     * Extract listener metadata from class using reflection.
     *
     * @param string $className
     * @return EventListenerMetadata|null
     */
    private function extractMetadata(string $className): ?EventListenerMetadata
    {
        try {
            $reflection = new ReflectionClass($className);
            $attributes = $reflection->getAttributes(AsEventListener::class);

            if (empty($attributes)) {
                return null;
            }

            $attribute = $attributes[0]->newInstance();

            return new EventListenerMetadata(
                className: $className,
                event: $attribute->event,
                priority: $attribute->priority,
            );
        } catch (\ReflectionException) {
            return null;
        }
    }

    /**
     * Extract fully qualified class name from PHP file.
     *
     * @param string $filePath
     * @return string|null
     */
    private function getClassNameFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        // Extract namespace
        if (!preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch)) {
            return null;
        }

        // Extract class name
        if (!preg_match('/class\s+(\w+)/', $content, $classMatch)) {
            return null;
        }

        return $namespaceMatch[1] . '\\' . $classMatch[1];
    }
}
```

### 4. Application Integration

**Modifications to Application::__construct():**

```php
public function __construct()
{
    parent::__construct('Seaman', '1.0.0');

    $projectRoot = getcwd();
    if ($projectRoot === false) {
        throw new \RuntimeException('Unable to determine current working directory');
    }

    // Setup EventDispatcher with auto-discovered listeners
    $dispatcher = $this->createEventDispatcher();
    $this->setDispatcher($dispatcher);

    // ... rest of existing code ...
}

private function createEventDispatcher(): EventDispatcher
{
    $dispatcher = new EventDispatcher();

    // Get listeners based on execution mode
    $listeners = $this->getEventListeners();

    // Register each listener with its priority
    foreach ($listeners as $metadata) {
        $listenerInstance = new $metadata->className();
        $dispatcher->addListener(
            $metadata->event,
            $listenerInstance,
            $metadata->priority
        );
    }

    return $dispatcher;
}

/**
 * Get event listeners based on execution mode.
 *
 * @return list<EventListenerMetadata>
 */
private function getEventListeners(): array
{
    if (\Phar::running()) {
        // PHAR: load from precompiled file
        $listenersFile = __DIR__ . '/../config/listeners.php';
        if (file_exists($listenersFile)) {
            return require $listenersFile;
        }
        return [];
    }

    // Development: auto-discovery
    $discovery = new ListenerDiscovery(__DIR__ . '/Listener');
    return $discovery->discover();
}
```

**New imports needed:**
```php
use Seaman\EventListener\ListenerDiscovery;
use Seaman\EventListener\EventListenerMetadata;
use Symfony\Component\EventDispatcher\EventDispatcher;
```

### 5. Generated config/listeners.php (for PHAR)

```php
<?php
// Auto-generated by BuildCommand - do not edit manually

declare(strict_types=1);

use Seaman\EventListener\EventListenerMetadata;

return [
    new EventListenerMetadata(
        className: \Seaman\Listener\LogCommandListener::class,
        event: 'console.command',
        priority: 100,
    ),
    new EventListenerMetadata(
        className: \Seaman\Listener\NotifyOnErrorListener::class,
        event: 'console.error',
        priority: 0,
    ),
    // ... more listeners ...
];
```

## Example Listeners

### LogCommandListener

```php
<?php

// ABOUTME: Logs command execution to error log.
// ABOUTME: Executes before all commands with high priority.

declare(strict_types=1);

namespace Seaman\Listener;

use Seaman\Attribute\AsEventListener;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\ConsoleEvents;

#[AsEventListener(event: ConsoleEvents::COMMAND, priority: 100)]
final readonly class LogCommandListener
{
    public function __invoke(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        $input = $event->getInput();

        error_log(sprintf(
            '[Seaman] Executing command: %s',
            $command?->getName() ?? 'unknown'
        ));
    }
}
```

### NotifyOnErrorListener

```php
<?php

// ABOUTME: Sends OS notification when command fails.
// ABOUTME: Uses Notifier service for desktop notifications.

declare(strict_types=1);

namespace Seaman\Listener;

use Seaman\Attribute\AsEventListener;
use Seaman\Notifier\Notifier;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\ConsoleEvents;

#[AsEventListener(event: ConsoleEvents::ERROR, priority: 0)]
final readonly class NotifyOnErrorListener
{
    public function __invoke(ConsoleErrorEvent $event): void
    {
        $error = $event->getError();
        $command = $event->getCommand();

        Notifier::error(sprintf(
            'Command "%s" failed: %s',
            $command?->getName() ?? 'unknown',
            $error->getMessage()
        ));
    }
}
```

## BuildCommand Modifications

The BuildCommand needs to generate the `config/listeners.php` file during PHAR build:

```php
private function generateListenersConfig(string $buildDir): void
{
    $discovery = new ListenerDiscovery(__DIR__ . '/Listener');
    $listeners = $discovery->discover();

    $configDir = $buildDir . '/config';
    if (!is_dir($configDir)) {
        mkdir($configDir, 0755, true);
    }

    $content = "<?php\n";
    $content .= "// Auto-generated by BuildCommand - do not edit manually\n\n";
    $content .= "declare(strict_types=1);\n\n";
    $content .= "use Seaman\\EventListener\\EventListenerMetadata;\n\n";
    $content .= "return [\n";

    foreach ($listeners as $metadata) {
        $content .= sprintf(
            "    new EventListenerMetadata(\n" .
            "        className: \\%s::class,\n" .
            "        event: '%s',\n" .
            "        priority: %d,\n" .
            "    ),\n",
            $metadata->className,
            $metadata->event,
            $metadata->priority
        );
    }

    $content .= "];\n";

    file_put_contents($configDir . '/listeners.php', $content);
}
```

## Testing Strategy

### Unit Tests

1. **AsEventListener Attribute:**
   - Test attribute can be instantiated
   - Test default priority is 0
   - Test attribute can be read via reflection

2. **EventListenerMetadata:**
   - Test value object creation
   - Test immutability
   - Test all properties are accessible

3. **ListenerDiscovery:**
   - Test discovers listeners in directory
   - Test reads attributes correctly
   - Test extracts priority correctly
   - Test ignores classes without attribute
   - Test handles invalid PHP files gracefully
   - Test sorts by priority (descending)

### Integration Tests

1. **Application with EventDispatcher:**
   - Test listeners are registered
   - Test listeners execute in priority order
   - Test listeners receive correct event
   - Test multiple listeners for same event work

2. **BuildCommand:**
   - Test generates valid listeners.php
   - Test generated file is loadable
   - Test generated file contains all listeners

3. **Example Listeners:**
   - Test LogCommandListener logs correctly
   - Test NotifyOnErrorListener sends notification
   - Test listeners don't break command execution

### PHAR Testing

1. Test PHAR loads listeners from config file
2. Test PHAR doesn't do filesystem discovery
3. Test all listeners work in PHAR mode

## Available Console Events

Symfony Console provides these events that can be used:

- **`ConsoleEvents::COMMAND`** (`console.command`)
  - Fired before command execution
  - Event: `ConsoleCommandEvent`
  - Use for: logging, validation, setup

- **`ConsoleEvents::TERMINATE`** (`console.terminate`)
  - Fired after command execution (success or failure)
  - Event: `ConsoleTerminateEvent`
  - Use for: cleanup, notifications, metrics

- **`ConsoleEvents::ERROR`** (`console.error`)
  - Fired when command throws exception
  - Event: `ConsoleErrorEvent`
  - Use for: error handling, notifications, recovery

- **`ConsoleEvents::SIGNAL`** (`console.signal`)
  - Fired when signal is received (SIGINT, etc.)
  - Event: `ConsoleSignalEvent`
  - Use for: graceful shutdown, cleanup

## Migration Path

### Phase 1: Core Implementation (this design)
- Implement attribute, discovery, and metadata
- Integrate with Application
- Add example listeners
- Update BuildCommand
- Write tests

### Phase 2: Common Listeners
- Add commonly needed listeners:
  - Configuration validation
  - Docker availability check
  - Cleanup on error
  - Performance metrics

### Phase 3: Documentation
- Document how to create listeners
- Document available events
- Add examples to README
- Create listener development guide

## Success Criteria

1. ✓ Listeners auto-discovered in development
2. ✓ Listeners precompiled in PHAR
3. ✓ Priority-based execution works
4. ✓ All tests pass with ≥95% coverage
5. ✓ PHPStan level 10 compliance
6. ✓ No performance degradation
7. ✓ Example listeners work correctly
8. ✓ BuildCommand generates valid config

## Future Enhancements (Out of Scope)

- Custom event types beyond Console events
- Conditional listener execution (filter by command name)
- Listener naming for debugging
- Event subscriber pattern (multiple events per class)
- Listener configuration from external file
- Async/deferred listeners
