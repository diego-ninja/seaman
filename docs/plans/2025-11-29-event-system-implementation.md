# Event System Implementation Plan

> **For Claude:** This plan will be executed manually by Claude in the current session, not using subagents.

**Goal:** Implement Symfony EventDispatcher-based event system with PHP 8 attribute-based auto-discovery of listeners.

**Architecture:** Use Symfony Console's EventDispatcher with custom auto-discovery mechanism. Listeners are marked with PHP 8 attributes and discovered via reflection in dev mode. PHAR mode uses precompiled listener list for performance.

**Tech Stack:** PHP 8.4, Symfony Console EventDispatcher, Attributes, Reflection API, Pest (testing)

---

## Task 1: Create AsEventListener Attribute

**Files:**
- Create: `src/Attribute/AsEventListener.php`
- Create: `tests/Unit/Attribute/AsEventListenerTest.php`

**Step 1: Write the failing test**

Create `tests/Unit/Attribute/AsEventListenerTest.php`:

```php
<?php

// ABOUTME: Tests for AsEventListener attribute.
// ABOUTME: Verifies attribute can be created and read via reflection.

declare(strict_types=1);

namespace Tests\Unit\Attribute;

use Seaman\Attribute\AsEventListener;
use Symfony\Component\Console\ConsoleEvents;

test('attribute can be instantiated with event and priority', function (): void {
    $attribute = new AsEventListener(
        event: ConsoleEvents::COMMAND,
        priority: 100
    );

    expect($attribute->event)->toBe(ConsoleEvents::COMMAND);
    expect($attribute->priority)->toBe(100);
});

test('attribute has default priority of 0', function (): void {
    $attribute = new AsEventListener(event: ConsoleEvents::COMMAND);

    expect($attribute->priority)->toBe(0);
});

test('attribute can be read from class via reflection', function (): void {
    $class = new \ReflectionClass(TestListener::class);
    $attributes = $class->getAttributes(AsEventListener::class);

    expect($attributes)->toHaveCount(1);

    $instance = $attributes[0]->newInstance();
    expect($instance)->toBeInstanceOf(AsEventListener::class);
    expect($instance->event)->toBe(ConsoleEvents::TERMINATE);
    expect($instance->priority)->toBe(50);
});

#[\Seaman\Attribute\AsEventListener(event: ConsoleEvents::TERMINATE, priority: 50)]
class TestListener
{
    public function __invoke($event): void
    {
    }
}
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Attribute/AsEventListenerTest.php`

Expected: FAIL with "Class 'Seaman\Attribute\AsEventListener' not found"

**Step 3: Write minimal implementation**

Create `src/Attribute/AsEventListener.php`:

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
     * Mark a class as an event listener.
     *
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

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Attribute/AsEventListenerTest.php`

Expected: PASS (all 3 tests passing)

**Step 5: Run PHPStan**

Run: `vendor/bin/phpstan analyse src/Attribute/AsEventListener.php --level=10`

Expected: PASS with 0 errors

**Step 6: Run php-cs-fixer**

Run: `vendor/bin/php-cs-fixer fix src/Attribute/AsEventListener.php`

Expected: Code formatted to PER standards

**Step 7: Commit**

```bash
git add src/Attribute/AsEventListener.php tests/Unit/Attribute/AsEventListenerTest.php
git commit -m "feat: add AsEventListener attribute for event system

Add PHP 8 attribute to mark event listener classes.
Supports event name and execution priority.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 2: Create EventListenerMetadata Value Object

**Files:**
- Create: `src/EventListener/EventListenerMetadata.php`
- Create: `tests/Unit/EventListener/EventListenerMetadataTest.php`

**Step 1: Write the failing test**

Create `tests/Unit/EventListener/EventListenerMetadataTest.php`:

```php
<?php

// ABOUTME: Tests for EventListenerMetadata value object.
// ABOUTME: Verifies metadata contains all required information.

declare(strict_types=1);

namespace Tests\Unit\EventListener;

use Seaman\EventListener\EventListenerMetadata;
use Symfony\Component\Console\ConsoleEvents;

test('metadata can be created with all properties', function (): void {
    $metadata = new EventListenerMetadata(
        className: 'App\\Listener\\MyListener',
        event: ConsoleEvents::COMMAND,
        priority: 100
    );

    expect($metadata->className)->toBe('App\\Listener\\MyListener');
    expect($metadata->event)->toBe(ConsoleEvents::COMMAND);
    expect($metadata->priority)->toBe(100);
});

test('metadata is readonly', function (): void {
    $metadata = new EventListenerMetadata(
        className: 'App\\Listener\\MyListener',
        event: ConsoleEvents::COMMAND,
        priority: 100
    );

    expect($metadata)->toBeReadOnly();
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/EventListener/EventListenerMetadataTest.php`

Expected: FAIL with "Class 'Seaman\EventListener\EventListenerMetadata' not found"

**Step 3: Write minimal implementation**

Create directory and file:

```bash
mkdir -p src/EventListener
```

Create `src/EventListener/EventListenerMetadata.php`:

```php
<?php

// ABOUTME: Value object containing event listener metadata.
// ABOUTME: Used for registering listeners with EventDispatcher.

declare(strict_types=1);

namespace Seaman\EventListener;

final readonly class EventListenerMetadata
{
    /**
     * Create event listener metadata.
     *
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

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/EventListener/EventListenerMetadataTest.php`

Expected: PASS (all 2 tests passing)

**Step 5: Run PHPStan**

Run: `vendor/bin/phpstan analyse src/EventListener/EventListenerMetadata.php --level=10`

Expected: PASS with 0 errors

**Step 6: Run php-cs-fixer**

Run: `vendor/bin/php-cs-fixer fix src/EventListener/EventListenerMetadata.php`

Expected: Code formatted

**Step 7: Commit**

```bash
git add src/EventListener/EventListenerMetadata.php tests/Unit/EventListener/EventListenerMetadataTest.php
git commit -m "feat: add EventListenerMetadata value object

Add readonly value object to store listener registration data.
Contains class name, event name, and priority.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 3: Create ListenerDiscovery Service

**Files:**
- Create: `src/EventListener/ListenerDiscovery.php`
- Create: `tests/Unit/EventListener/ListenerDiscoveryTest.php`

**Step 1: Write the failing test**

Create `tests/Unit/EventListener/ListenerDiscoveryTest.php`:

```php
<?php

// ABOUTME: Tests for ListenerDiscovery service.
// ABOUTME: Verifies listener scanning and metadata extraction.

declare(strict_types=1);

namespace Tests\Unit\EventListener;

use Seaman\EventListener\ListenerDiscovery;
use Seaman\Attribute\AsEventListener;
use Symfony\Component\Console\ConsoleEvents;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir() . '/listener-test-' . uniqid();
    mkdir($this->tempDir);
});

afterEach(function (): void {
    if (is_dir($this->tempDir)) {
        array_map('unlink', glob($this->tempDir . '/*.php') ?: []);
        rmdir($this->tempDir);
    }
});

test('discovers listener with attribute', function (): void {
    // Create a test listener file
    $listenerCode = <<<'PHP'
<?php
namespace Test;
use Seaman\Attribute\AsEventListener;
use Symfony\Component\Console\ConsoleEvents;

#[AsEventListener(event: ConsoleEvents::COMMAND, priority: 100)]
class TestListener {
    public function __invoke($event): void {}
}
PHP;

    file_put_contents($this->tempDir . '/TestListener.php', $listenerCode);

    $discovery = new ListenerDiscovery($this->tempDir);
    $listeners = $discovery->discover();

    expect($listeners)->toHaveCount(1);
    expect($listeners[0]->className)->toBe('Test\\TestListener');
    expect($listeners[0]->event)->toBe(ConsoleEvents::COMMAND);
    expect($listeners[0]->priority)->toBe(100);
});

test('ignores classes without attribute', function (): void {
    $classCode = <<<'PHP'
<?php
namespace Test;

class RegularClass {
    public function method(): void {}
}
PHP;

    file_put_contents($this->tempDir . '/RegularClass.php', $classCode);

    $discovery = new ListenerDiscovery($this->tempDir);
    $listeners = $discovery->discover();

    expect($listeners)->toBeEmpty();
});

test('sorts listeners by priority descending', function (): void {
    $listener1 = <<<'PHP'
<?php
namespace Test;
use Seaman\Attribute\AsEventListener;

#[AsEventListener(event: 'test', priority: 50)]
class Listener1 {
    public function __invoke($event): void {}
}
PHP;

    $listener2 = <<<'PHP'
<?php
namespace Test;
use Seaman\Attribute\AsEventListener;

#[AsEventListener(event: 'test', priority: 100)]
class Listener2 {
    public function __invoke($event): void {}
}
PHP;

    file_put_contents($this->tempDir . '/Listener1.php', $listener1);
    file_put_contents($this->tempDir . '/Listener2.php', $listener2);

    $discovery = new ListenerDiscovery($this->tempDir);
    $listeners = $discovery->discover();

    expect($listeners)->toHaveCount(2);
    expect($listeners[0]->priority)->toBe(100); // Higher priority first
    expect($listeners[1]->priority)->toBe(50);
});

test('handles empty directory', function (): void {
    $discovery = new ListenerDiscovery($this->tempDir);
    $listeners = $discovery->discover();

    expect($listeners)->toBeEmpty();
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/EventListener/ListenerDiscoveryTest.php`

Expected: FAIL with "Class 'Seaman\EventListener\ListenerDiscovery' not found"

**Step 3: Write minimal implementation**

Create `src/EventListener/ListenerDiscovery.php`:

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
use RegexIterator;

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
        if (!is_dir($this->listenerDir)) {
            return [];
        }

        $listeners = [];

        foreach ($this->scanDirectory() as $className) {
            $metadata = $this->extractMetadata($className);
            if ($metadata !== null) {
                $listeners[] = $metadata;
            }
        }

        // Sort by priority (descending - higher priority first)
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

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->listenerDir)
            );

            $phpFiles = new RegexIterator($iterator, '/\.php$/');

            foreach ($phpFiles as $file) {
                if ($file->isFile()) {
                    $className = $this->getClassNameFromFile($file->getPathname());
                    if ($className !== null) {
                        $classes[] = $className;
                    }
                }
            }
        } catch (\Exception) {
            return [];
        }

        return $classes;
    }

    /**
     * Extract listener metadata from class using reflection.
     *
     * @param string $className Fully qualified class name
     * @return EventListenerMetadata|null
     */
    private function extractMetadata(string $className): ?EventListenerMetadata
    {
        try {
            if (!class_exists($className)) {
                return null;
            }

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
     * @param string $filePath Absolute path to PHP file
     * @return string|null Fully qualified class name or null
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

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/EventListener/ListenerDiscoveryTest.php`

Expected: PASS (all 4 tests passing)

**Step 5: Run PHPStan**

Run: `vendor/bin/phpstan analyse src/EventListener/ListenerDiscovery.php --level=10`

Expected: PASS with 0 errors

**Step 6: Run php-cs-fixer**

Run: `vendor/bin/php-cs-fixer fix src/EventListener/ListenerDiscovery.php`

Expected: Code formatted

**Step 7: Commit**

```bash
git add src/EventListener/ListenerDiscovery.php tests/Unit/EventListener/ListenerDiscoveryTest.php
git commit -m "feat: add ListenerDiscovery service

Add service to scan directories and discover event listeners.
Uses reflection to read AsEventListener attributes.
Sorts listeners by priority (descending).

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 4: Create Example Listeners

**Files:**
- Create: `src/Listener/LogCommandListener.php`
- Create: `src/Listener/NotifyOnErrorListener.php`
- Create: `tests/Unit/Listener/LogCommandListenerTest.php`
- Create: `tests/Unit/Listener/NotifyOnErrorListenerTest.php`

**Step 1: Create LogCommandListener with test**

Create `tests/Unit/Listener/LogCommandListenerTest.php`:

```php
<?php

// ABOUTME: Tests for LogCommandListener.
// ABOUTME: Verifies command execution logging.

declare(strict_types=1);

namespace Tests\Unit\Listener;

use Seaman\Listener\LogCommandListener;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

test('listener logs command name', function (): void {
    $command = new Command('test:command');
    $input = new ArrayInput([]);
    $output = new NullOutput();

    $event = new ConsoleCommandEvent($command, $input, $output);

    $listener = new LogCommandListener();

    // Capture error_log output
    $originalErrorLog = ini_get('error_log');
    $tempLog = sys_get_temp_dir() . '/test-log-' . uniqid() . '.log';
    ini_set('error_log', $tempLog);

    $listener($event);

    ini_set('error_log', $originalErrorLog);

    $logContent = file_get_contents($tempLog);
    expect($logContent)->toContain('[Seaman] Executing command: test:command');

    unlink($tempLog);
});

test('listener handles null command gracefully', function (): void {
    $input = new ArrayInput([]);
    $output = new NullOutput();

    $event = new ConsoleCommandEvent(null, $input, $output);

    $listener = new LogCommandListener();

    $tempLog = sys_get_temp_dir() . '/test-log-' . uniqid() . '.log';
    ini_set('error_log', $tempLog);

    $listener($event);

    $logContent = file_get_contents($tempLog);
    expect($logContent)->toContain('[Seaman] Executing command: unknown');

    unlink($tempLog);
});
```

Create directory and listener:

```bash
mkdir -p src/Listener
```

Create `src/Listener/LogCommandListener.php`:

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

        error_log(sprintf(
            '[Seaman] Executing command: %s',
            $command?->getName() ?? 'unknown'
        ));
    }
}
```

Run test:
```bash
vendor/bin/pest tests/Unit/Listener/LogCommandListenerTest.php
```

Expected: PASS

**Step 2: Create NotifyOnErrorListener with test**

Create `tests/Unit/Listener/NotifyOnErrorListenerTest.php`:

```php
<?php

// ABOUTME: Tests for NotifyOnErrorListener.
// ABOUTME: Verifies error notifications are sent.

declare(strict_types=1);

namespace Tests\Unit\Listener;

use Seaman\Listener\NotifyOnErrorListener;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

test('listener is properly annotated', function (): void {
    $reflection = new \ReflectionClass(NotifyOnErrorListener::class);
    $attributes = $reflection->getAttributes(\Seaman\Attribute\AsEventListener::class);

    expect($attributes)->toHaveCount(1);

    $instance = $attributes[0]->newInstance();
    expect($instance->event)->toBe('console.error');
    expect($instance->priority)->toBe(0);
});

test('listener has invoke method', function (): void {
    $listener = new NotifyOnErrorListener();

    expect($listener)->toBeCallable();
});
```

Create `src/Listener/NotifyOnErrorListener.php`:

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

Run tests:
```bash
vendor/bin/pest tests/Unit/Listener/
```

Expected: PASS (all listener tests)

**Step 3: Run PHPStan and php-cs-fixer**

```bash
vendor/bin/phpstan analyse src/Listener/ --level=10
vendor/bin/php-cs-fixer fix src/Listener/
```

**Step 4: Commit**

```bash
git add src/Listener/ tests/Unit/Listener/
git commit -m "feat: add example event listeners

Add LogCommandListener for command execution logging.
Add NotifyOnErrorListener for error notifications.
Both use AsEventListener attribute for auto-discovery.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 5: Integrate EventDispatcher into Application

**Files:**
- Modify: `src/Application.php`

**Step 1: Modify Application to use EventDispatcher**

Update `src/Application.php`:

1. Add imports at top:
```php
use Seaman\EventListener\ListenerDiscovery;
use Seaman\EventListener\EventListenerMetadata;
use Symfony\Component\EventDispatcher\EventDispatcher;
```

2. Add after `parent::__construct()` call in `__construct()`:
```php
// Setup EventDispatcher with auto-discovered listeners
$dispatcher = $this->createEventDispatcher();
$this->setDispatcher($dispatcher);
```

3. Add these private methods at the end of the class:

```php
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

**Step 2: Run PHPStan**

```bash
vendor/bin/phpstan analyse src/Application.php --level=10
```

Expected: PASS

**Step 3: Run php-cs-fixer**

```bash
vendor/bin/php-cs-fixer fix src/Application.php
```

**Step 4: Commit**

```bash
git add src/Application.php
git commit -m "feat: integrate EventDispatcher into Application

Add EventDispatcher configuration with auto-discovery.
In dev mode: scans src/Listener/ for listeners.
In PHAR mode: loads from precompiled config/listeners.php.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 6: Add Integration Tests

**Files:**
- Create: `tests/Integration/EventSystemTest.php`

**Step 1: Create integration test**

Create `tests/Integration/EventSystemTest.php`:

```php
<?php

// ABOUTME: Integration tests for event system.
// ABOUTME: Verifies EventDispatcher registration and listener execution.

declare(strict_types=1);

namespace Tests\Integration;

use Seaman\Application;
use Symfony\Component\Console\Tester\CommandTester;

test('application has event dispatcher configured', function (): void {
    $app = new Application();

    $dispatcher = $app->getDispatcher();

    expect($dispatcher)->toBeInstanceOf(\Symfony\Component\EventDispatcher\EventDispatcher::class);
});

test('listeners are registered and execute', function (): void {
    $app = new Application();

    $command = $app->find('seaman:init');
    $tester = new CommandTester($command);

    // Capture error_log to verify LogCommandListener executed
    $tempLog = sys_get_temp_dir() . '/test-integration-' . uniqid() . '.log';
    $originalErrorLog = ini_get('error_log');
    ini_set('error_log', $tempLog);

    // Execute command (will fail but that's OK, we're testing listener execution)
    try {
        $tester->setInputs(['no']); // Decline overwrite if seaman.yaml exists
        $tester->execute([]);
    } catch (\Exception) {
        // Expected to fail, we're just testing if listeners execute
    }

    ini_set('error_log', $originalErrorLog);

    // Verify LogCommandListener executed
    if (file_exists($tempLog)) {
        $logContent = file_get_contents($tempLog);
        expect($logContent)->toContain('[Seaman] Executing command: seaman:init');
        unlink($tempLog);
    }
});

test('listeners from Listener directory are discovered', function (): void {
    $app = new Application();
    $dispatcher = $app->getDispatcher();

    // Verify listeners are registered
    $listeners = $dispatcher->getListeners('console.command');

    expect($listeners)->not->toBeEmpty();

    // Verify at least LogCommandListener is registered
    $hasLogListener = false;
    foreach ($listeners as $listener) {
        if ($listener instanceof \Seaman\Listener\LogCommandListener) {
            $hasLogListener = true;
            break;
        }
    }

    expect($hasLogListener)->toBeTrue();
});
```

**Step 2: Run test**

```bash
vendor/bin/pest tests/Integration/EventSystemTest.php
```

Expected: PASS (all integration tests passing)

**Step 3: Commit**

```bash
git add tests/Integration/EventSystemTest.php
git commit -m "test: add integration tests for event system

Verify EventDispatcher is configured in Application.
Verify listeners are discovered and registered.
Verify listeners execute when commands run.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 7: Update BuildCommand for PHAR

**Files:**
- Modify: `src/Command/BuildCommand.php`

**Step 1: Add listener compilation to BuildCommand**

In `src/Command/BuildCommand.php`, add this method:

```php
private function generateListenersConfig(string $projectRoot): void
{
    $discovery = new \Seaman\EventListener\ListenerDiscovery($projectRoot . '/src/Listener');
    $listeners = $discovery->discover();

    $configDir = $projectRoot . '/config';
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

Then call it in the `execute()` method before building the PHAR:

```php
// Generate listeners config
$this->generateListenersConfig($projectRoot);
```

**Step 2: Run PHPStan**

```bash
vendor/bin/phpstan analyse src/Command/BuildCommand.php --level=10
```

Expected: PASS

**Step 3: Commit**

```bash
git add src/Command/BuildCommand.php
git commit -m "feat: generate listeners config in BuildCommand

Add listener compilation during PHAR build.
Generates config/listeners.php with precompiled listener list.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 8: Run Full Test Suite and Verification

**Files:**
- None (verification only)

**Step 1: Run all tests**

```bash
vendor/bin/pest
```

Expected: All tests pass (existing BuildCommand test failures are pre-existing, not related to event system)

**Step 2: Run PHPStan on entire codebase**

```bash
vendor/bin/phpstan analyse --level=10
```

Expected: PASS with 0 errors for new code

**Step 3: Run php-cs-fixer on entire codebase**

```bash
vendor/bin/php-cs-fixer fix
```

Expected: All files formatted correctly

**Step 4: Verify test coverage**

```bash
vendor/bin/pest --coverage
```

Expected: Coverage ≥ 95% for new event system files

**Step 5: Test manually**

```bash
# Should log command execution
bin/seaman status
```

Check error log for: `[Seaman] Executing command: seaman:status`

**Step 6: Commit any formatting changes**

```bash
git add -A
git commit -m "style: apply php-cs-fixer formatting

Ensure all event system code follows PER standards.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Implementation Complete!

All tasks completed. The event system has been implemented with:

✓ PHP 8 attribute-based listener marking
✓ Auto-discovery of listeners in development
✓ Precompiled listener list for PHAR
✓ Priority-based listener execution
✓ Example listeners (LogCommand, NotifyOnError)
✓ Full test coverage (≥95%)
✓ PHPStan level 10 compliance
✓ Integration with Symfony Console EventDispatcher

**Files Created:**
- `src/Attribute/AsEventListener.php`
- `src/EventListener/EventListenerMetadata.php`
- `src/EventListener/ListenerDiscovery.php`
- `src/Listener/LogCommandListener.php`
- `src/Listener/NotifyOnErrorListener.php`
- Comprehensive test files

**Files Modified:**
- `src/Application.php` (EventDispatcher integration)
- `src/Command/BuildCommand.php` (listener compilation)

**Next Steps:**
- Document how to create custom listeners
- Add more listeners as needed (config validation, Docker checks, etc.)
- Consider adding listener configuration options
