# Headless UI System Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Create a testable UI abstraction layer that enables integration testing of interactive commands.

**Architecture:** Wrapper classes over Laravel Prompts and Spinner that detect headless mode (CI/tests) and use preset responses or defaults instead of interactive prompts. Terminal adapts output based on detected capabilities.

**Tech Stack:** PHP 8.4, Laravel Prompts, Pest testing framework, PHPStan level 10

---

## Task 1: Create HeadlessMode State Manager

**Files:**
- Create: `src/UI/HeadlessMode.php`
- Test: `tests/Unit/UI/HeadlessModeTest.php`

**Step 1: Write the failing test**

Create `tests/Unit/UI/HeadlessModeTest.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for HeadlessMode state management.
// ABOUTME: Validates mode detection, preset responses, and reset functionality.

namespace Seaman\Tests\Unit\UI;

use Seaman\UI\HeadlessMode;

beforeEach(function (): void {
    HeadlessMode::reset();
});

afterEach(function (): void {
    HeadlessMode::reset();
});

test('isHeadless returns false by default in TTY', function (): void {
    // In test environment without explicit enable, detection depends on environment
    // We test the explicit enable/disable behavior instead
    HeadlessMode::disable();
    HeadlessMode::forceInteractive(true);

    expect(HeadlessMode::isHeadless())->toBeFalse();
});

test('enable sets headless mode', function (): void {
    HeadlessMode::enable();

    expect(HeadlessMode::isHeadless())->toBeTrue();
});

test('forceInteractive overrides headless', function (): void {
    HeadlessMode::enable();
    HeadlessMode::forceInteractive(true);

    expect(HeadlessMode::isHeadless())->toBeFalse();
});

test('preset stores responses', function (): void {
    HeadlessMode::preset([
        'Select database' => 'mysql',
        'Enable Xdebug?' => true,
    ]);

    expect(HeadlessMode::hasPreset('Select database'))->toBeTrue();
    expect(HeadlessMode::getPreset('Select database'))->toBe('mysql');
    expect(HeadlessMode::hasPreset('Enable Xdebug?'))->toBeTrue();
    expect(HeadlessMode::getPreset('Enable Xdebug?'))->toBe(true);
});

test('hasPreset returns false for missing keys', function (): void {
    expect(HeadlessMode::hasPreset('Unknown prompt'))->toBeFalse();
});

test('getPreset returns null for missing keys', function (): void {
    expect(HeadlessMode::getPreset('Unknown prompt'))->toBeNull();
});

test('preset merges with existing responses', function (): void {
    HeadlessMode::preset(['first' => 'value1']);
    HeadlessMode::preset(['second' => 'value2']);

    expect(HeadlessMode::getPreset('first'))->toBe('value1');
    expect(HeadlessMode::getPreset('second'))->toBe('value2');
});

test('reset clears all state', function (): void {
    HeadlessMode::enable();
    HeadlessMode::forceInteractive(true);
    HeadlessMode::preset(['key' => 'value']);

    HeadlessMode::reset();

    expect(HeadlessMode::hasPreset('key'))->toBeFalse();
    // After reset, detection runs fresh
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/UI/HeadlessModeTest.php`
Expected: FAIL with "Class 'Seaman\UI\HeadlessMode' not found"

**Step 3: Write the implementation**

Create `src/UI/HeadlessMode.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Manages headless mode state for UI components.
// ABOUTME: Detects CI/test environments and stores preset responses.

namespace Seaman\UI;

final class HeadlessMode
{
    private static bool $enabled = false;
    private static bool $forceInteractive = false;
    private static ?bool $detected = null;

    /** @var array<string, mixed> */
    private static array $presetResponses = [];

    /**
     * Enable headless mode explicitly.
     */
    public static function enable(): void
    {
        self::$enabled = true;
    }

    /**
     * Disable headless mode (return to auto-detection).
     */
    public static function disable(): void
    {
        self::$enabled = false;
    }

    /**
     * Force interactive mode even without TTY.
     */
    public static function forceInteractive(bool $force = true): void
    {
        self::$forceInteractive = $force;
    }

    /**
     * Check if running in headless mode.
     */
    public static function isHeadless(): bool
    {
        if (self::$forceInteractive) {
            return false;
        }
        if (self::$enabled) {
            return true;
        }

        return self::detect();
    }

    /**
     * Auto-detect headless mode from environment.
     */
    private static function detect(): bool
    {
        if (self::$detected === null) {
            self::$detected =
                getenv('SEAMAN_HEADLESS') === '1' ||
                getenv('CI') === 'true' ||
                !stream_isatty(STDIN);
        }

        return self::$detected;
    }

    /**
     * Preset responses for prompts (used in tests).
     *
     * @param array<string, mixed> $responses Map of label => response
     */
    public static function preset(array $responses): void
    {
        self::$presetResponses = array_merge(self::$presetResponses, $responses);
    }

    /**
     * Get preset response for a label.
     */
    public static function getPreset(string $label): mixed
    {
        return self::$presetResponses[$label] ?? null;
    }

    /**
     * Check if a preset exists for a label.
     */
    public static function hasPreset(string $label): bool
    {
        return array_key_exists($label, self::$presetResponses);
    }

    /**
     * Reset all state (call between tests).
     */
    public static function reset(): void
    {
        self::$enabled = false;
        self::$forceInteractive = false;
        self::$detected = null;
        self::$presetResponses = [];
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/UI/HeadlessModeTest.php`
Expected: PASS (all 8 tests)

**Step 5: Run PHPStan**

Run: `vendor/bin/phpstan analyse src/UI/HeadlessMode.php --level=10`
Expected: No errors

**Step 6: Commit**

```bash
git add src/UI/HeadlessMode.php tests/Unit/UI/HeadlessModeTest.php
git commit -m "feat: add HeadlessMode state manager"
```

---

## Task 2: Create HeadlessModeException

**Files:**
- Create: `src/Exception/HeadlessModeException.php`
- Test: `tests/Unit/Exception/HeadlessModeExceptionTest.php`

**Step 1: Write the failing test**

Create `tests/Unit/Exception/HeadlessModeExceptionTest.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for HeadlessModeException.
// ABOUTME: Validates exception creation with proper messages.

namespace Seaman\Tests\Unit\Exception;

use Seaman\Exception\HeadlessModeException;

test('missingDefault creates exception with label', function (): void {
    $exception = HeadlessModeException::missingDefault('Select database');

    expect($exception)->toBeInstanceOf(HeadlessModeException::class);
    expect($exception->getMessage())->toContain('Select database');
    expect($exception->getMessage())->toContain('No default');
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Exception/HeadlessModeExceptionTest.php`
Expected: FAIL with "Class 'Seaman\Exception\HeadlessModeException' not found"

**Step 3: Write the implementation**

Create `src/Exception/HeadlessModeException.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Exception thrown when headless mode lacks required values.
// ABOUTME: Occurs when a prompt has no default and no preset response.

namespace Seaman\Exception;

use RuntimeException;

final class HeadlessModeException extends RuntimeException
{
    public static function missingDefault(string $label): self
    {
        return new self(sprintf(
            'No default value for required prompt in headless mode: %s',
            $label,
        ));
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Exception/HeadlessModeExceptionTest.php`
Expected: PASS

**Step 5: Run PHPStan**

Run: `vendor/bin/phpstan analyse src/Exception/HeadlessModeException.php --level=10`
Expected: No errors

**Step 6: Commit**

```bash
git add src/Exception/HeadlessModeException.php tests/Unit/Exception/HeadlessModeExceptionTest.php
git commit -m "feat: add HeadlessModeException"
```

---

## Task 3: Create Prompts Wrapper

**Files:**
- Create: `src/UI/Prompts.php`
- Test: `tests/Unit/UI/PromptsTest.php`

**Step 1: Write the failing test**

Create `tests/Unit/UI/PromptsTest.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for Prompts wrapper in headless mode.
// ABOUTME: Validates that prompts use presets and defaults correctly.

namespace Seaman\Tests\Unit\UI;

use Seaman\Exception\HeadlessModeException;
use Seaman\UI\HeadlessMode;
use Seaman\UI\Prompts;

beforeEach(function (): void {
    HeadlessMode::reset();
    HeadlessMode::enable();
});

afterEach(function (): void {
    HeadlessMode::reset();
});

// confirm() tests

test('confirm returns preset value when available', function (): void {
    HeadlessMode::preset(['Enable feature?' => true]);

    $result = Prompts::confirm('Enable feature?', default: false);

    expect($result)->toBeTrue();
});

test('confirm returns default when no preset', function (): void {
    $result = Prompts::confirm('Enable feature?', default: true);

    expect($result)->toBeTrue();
});

test('confirm returns false default when no preset', function (): void {
    $result = Prompts::confirm('Enable feature?', default: false);

    expect($result)->toBeFalse();
});

// select() tests

test('select returns preset value when available', function (): void {
    HeadlessMode::preset(['Choose option' => 'b']);

    $result = Prompts::select(
        'Choose option',
        options: ['a' => 'Option A', 'b' => 'Option B'],
        default: 'a',
    );

    expect($result)->toBe('b');
});

test('select returns default when no preset', function (): void {
    $result = Prompts::select(
        'Choose option',
        options: ['a' => 'Option A', 'b' => 'Option B'],
        default: 'a',
    );

    expect($result)->toBe('a');
});

test('select throws when no default and no preset', function (): void {
    Prompts::select(
        'Choose option',
        options: ['a' => 'Option A', 'b' => 'Option B'],
        default: null,
    );
})->throws(HeadlessModeException::class, 'Choose option');

// multiselect() tests

test('multiselect returns preset value when available', function (): void {
    HeadlessMode::preset(['Select services' => ['redis', 'mailpit']]);

    $result = Prompts::multiselect(
        'Select services',
        options: ['redis' => 'Redis', 'mailpit' => 'Mailpit', 'minio' => 'Minio'],
        default: [],
    );

    expect($result)->toBe(['redis', 'mailpit']);
});

test('multiselect returns default when no preset', function (): void {
    $result = Prompts::multiselect(
        'Select services',
        options: ['redis' => 'Redis', 'mailpit' => 'Mailpit'],
        default: ['redis'],
    );

    expect($result)->toBe(['redis']);
});

// text() tests

test('text returns preset value when available', function (): void {
    HeadlessMode::preset(['Enter name' => 'my-project']);

    $result = Prompts::text('Enter name', default: 'default-name');

    expect($result)->toBe('my-project');
});

test('text returns default when no preset', function (): void {
    $result = Prompts::text('Enter name', default: 'default-name');

    expect($result)->toBe('default-name');
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/UI/PromptsTest.php`
Expected: FAIL with "Class 'Seaman\UI\Prompts' not found"

**Step 3: Write the implementation**

Create `src/UI/Prompts.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Wrapper over Laravel Prompts with headless mode support.
// ABOUTME: Uses preset responses or defaults when not running interactively.

namespace Seaman\UI;

use Seaman\Exception\HeadlessModeException;

final class Prompts
{
    /**
     * Confirm prompt (yes/no).
     */
    public static function confirm(
        string $label,
        bool $default = false,
        string $hint = '',
    ): bool {
        if (HeadlessMode::isHeadless()) {
            if (HeadlessMode::hasPreset($label)) {
                return (bool) HeadlessMode::getPreset($label);
            }

            return $default;
        }

        return \Laravel\Prompts\confirm(
            label: $label,
            default: $default,
            hint: $hint,
        );
    }

    /**
     * Single selection prompt.
     *
     * @param array<string, string>|list<string> $options
     */
    public static function select(
        string $label,
        array $options,
        ?string $default = null,
        string $hint = '',
    ): string {
        if (HeadlessMode::isHeadless()) {
            if (HeadlessMode::hasPreset($label)) {
                return (string) HeadlessMode::getPreset($label);
            }
            if ($default === null) {
                throw HeadlessModeException::missingDefault($label);
            }

            return $default;
        }

        return \Laravel\Prompts\select(
            label: $label,
            options: $options,
            default: $default,
            hint: $hint,
        );
    }

    /**
     * Multiple selection prompt.
     *
     * @param array<string, string>|list<string> $options
     * @param list<string> $default
     * @return list<string>
     */
    public static function multiselect(
        string $label,
        array $options,
        array $default = [],
        string $hint = '',
        bool $required = false,
    ): array {
        if (HeadlessMode::isHeadless()) {
            if (HeadlessMode::hasPreset($label)) {
                $preset = HeadlessMode::getPreset($label);

                return is_array($preset) ? array_values($preset) : [$preset];
            }

            return $default;
        }

        /** @var list<string> */
        return \Laravel\Prompts\multiselect(
            label: $label,
            options: $options,
            default: $default,
            hint: $hint,
            required: $required,
        );
    }

    /**
     * Text input prompt.
     */
    public static function text(
        string $label,
        string $default = '',
        string $placeholder = '',
        string $hint = '',
    ): string {
        if (HeadlessMode::isHeadless()) {
            if (HeadlessMode::hasPreset($label)) {
                return (string) HeadlessMode::getPreset($label);
            }

            return $default;
        }

        return \Laravel\Prompts\text(
            label: $label,
            default: $default,
            placeholder: $placeholder,
            hint: $hint,
        );
    }

    /**
     * Display info message.
     */
    public static function info(string $message): void
    {
        if (HeadlessMode::isHeadless()) {
            Terminal::output()->writeln("  ℹ {$message}");

            return;
        }

        \Laravel\Prompts\info($message);
    }

    /**
     * Display table.
     *
     * @param list<string> $headers
     * @param list<list<string>> $rows
     */
    public static function table(array $headers, array $rows): void
    {
        if (HeadlessMode::isHeadless()) {
            $output = Terminal::output();
            $output->writeln(implode(' | ', $headers));
            $output->writeln(str_repeat('-', 60));
            foreach ($rows as $row) {
                $output->writeln(implode(' | ', $row));
            }

            return;
        }

        \Laravel\Prompts\table($headers, $rows);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/UI/PromptsTest.php`
Expected: PASS (all 10 tests)

**Step 5: Run PHPStan**

Run: `vendor/bin/phpstan analyse src/UI/Prompts.php --level=10`
Expected: No errors

**Step 6: Commit**

```bash
git add src/UI/Prompts.php tests/Unit/UI/PromptsTest.php
git commit -m "feat: add Prompts wrapper with headless mode support"
```

---

## Task 4: Add Terminal Capability Detection

**Files:**
- Modify: `src/UI/Terminal.php`
- Test: `tests/Unit/UI/TerminalTest.php`

**Step 1: Write the failing test**

Create `tests/Unit/UI/TerminalTest.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for Terminal capability detection.
// ABOUTME: Validates ANSI and cursor support detection.

namespace Seaman\Tests\Unit\UI;

use Seaman\UI\HeadlessMode;
use Seaman\UI\Terminal;

beforeEach(function (): void {
    HeadlessMode::reset();
});

afterEach(function (): void {
    HeadlessMode::reset();
});

test('supportsCursor returns false in headless mode', function (): void {
    HeadlessMode::enable();

    expect(Terminal::supportsCursor())->toBeFalse();
});

test('supportsAnsi returns boolean', function (): void {
    // Just verify it returns a boolean without error
    $result = Terminal::supportsAnsi();

    expect($result)->toBeBool();
});

test('success outputs message', function (): void {
    HeadlessMode::enable();

    ob_start();
    Terminal::success('Test message');
    $output = ob_get_clean();

    // In headless mode with output buffering, we verify no exception is thrown
    expect(true)->toBeTrue();
});

test('error outputs message', function (): void {
    HeadlessMode::enable();

    ob_start();
    Terminal::error('Test error');
    $output = ob_get_clean();

    // In headless mode with output buffering, we verify no exception is thrown
    expect(true)->toBeTrue();
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/UI/TerminalTest.php`
Expected: FAIL with "Call to undefined method Seaman\UI\Terminal::supportsCursor()"

**Step 3: Modify Terminal.php**

Add to `src/UI/Terminal.php` (add these methods and modify existing ones):

```php
// Add this property after the existing $instance property
private static ?bool $supportsAnsi = null;

/**
 * Check if terminal supports ANSI (colors, etc.)
 */
public static function supportsAnsi(): bool
{
    if (self::$supportsAnsi === null) {
        self::$supportsAnsi = self::output()->isDecorated();
    }

    return self::$supportsAnsi;
}

/**
 * Check if we can manipulate cursor (hide, clear line, etc.)
 * Requires real TTY, not just ANSI support.
 */
public static function supportsCursor(): bool
{
    if (HeadlessMode::isHeadless()) {
        return false;
    }

    return posix_isatty(STDOUT);
}

// Modify existing success() method:
public static function success(string $message): void
{
    $symbol = self::supportsAnsi()
        ? '<fg=bright-green>⬡</>'
        : '⬡';

    self::output()->writeln(sprintf(
        "%s%s %s",
        str_repeat(' ', 2),
        $symbol,
        $message,
    ));
}

// Modify existing error() method:
public static function error(string $message): void
{
    $symbol = self::supportsAnsi()
        ? '<fg=bright-red>⬡</>'
        : '⬡';
    $text = self::supportsAnsi()
        ? "<fg=bright-red>{$message}</>"
        : $message;

    self::output()->writeln(sprintf(
        "%s%s %s",
        str_repeat(' ', 2),
        $symbol,
        $text,
    ));
}

// Modify existing hideCursor() method:
public static function hideCursor(mixed $stream = STDOUT): void
{
    if (!self::supportsCursor()) {
        return;
    }
    fprintf($stream, "\033[?25l");
    register_shutdown_function(static function (): void {
        self::restoreCursor();
    });
}

// Modify existing clear() method:
public static function clear(int $lines): void
{
    if (!self::supportsCursor()) {
        return;
    }
    for ($i = 0; $i < $lines; $i++) {
        self::output()->write("\033[1A");
        self::output()->write("\033[2K");
    }

    self::output()->write("\033[1G");
}
```

Also add the use statement at the top:
```php
use Seaman\UI\HeadlessMode;
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/UI/TerminalTest.php`
Expected: PASS (all 4 tests)

**Step 5: Run PHPStan**

Run: `vendor/bin/phpstan analyse src/UI/Terminal.php --level=10`
Expected: No errors

**Step 6: Commit**

```bash
git add src/UI/Terminal.php tests/Unit/UI/TerminalTest.php
git commit -m "feat: add Terminal capability detection for headless mode"
```

---

## Task 5: Modify Spinner for Headless Mode

**Files:**
- Modify: `src/UI/Widget/Spinner/Spinner.php`
- Test: `tests/Unit/UI/Widget/Spinner/SpinnerTest.php`

**Step 1: Write the failing test**

Create `tests/Unit/UI/Widget/Spinner/SpinnerTest.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Tests for Spinner in headless mode.
// ABOUTME: Validates static output without forking.

namespace Seaman\Tests\Unit\UI\Widget\Spinner;

use Seaman\UI\HeadlessMode;
use Seaman\UI\Widget\Spinner\Spinner;

beforeEach(function (): void {
    HeadlessMode::reset();
    HeadlessMode::enable();
});

afterEach(function (): void {
    HeadlessMode::reset();
});

test('callback executes in headless mode without forking', function (): void {
    $spinner = new Spinner();
    $spinner->setMessage('Test operation');

    $executed = false;
    $result = $spinner->callback(function () use (&$executed): bool {
        $executed = true;
        return true;
    });

    expect($executed)->toBeTrue();
    expect($result)->toBeTrue();
});

test('callback returns false result in headless mode', function (): void {
    $spinner = new Spinner();
    $spinner->setMessage('Failing operation');

    $result = $spinner->callback(fn(): bool => false);

    expect($result)->toBeFalse();
});

test('callback returns callback result in headless mode', function (): void {
    $spinner = new Spinner();
    $spinner->setMessage('String operation');

    $result = $spinner->callback(fn(): string => 'test-value');

    expect($result)->toBe('test-value');
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/UI/Widget/Spinner/SpinnerTest.php`
Expected: Tests may pass or fail depending on current implementation. If they pass, the implementation already works.

**Step 3: Modify Spinner.php**

Modify `src/UI/Widget/Spinner/Spinner.php`:

Add at the top:
```php
use Seaman\UI\HeadlessMode;
```

Replace the `callback()` method:
```php
/**
 * @throws Exception
 */
public function callback(callable $callback): mixed
{
    // Headless: static output without fork
    if (HeadlessMode::isHeadless() || !extension_loaded('pcntl') || !posix_isatty(STDOUT)) {
        return $this->runHeadless($callback);
    }

    return $this->runInteractive($callback);
}

private function runHeadless(callable $callback): mixed
{
    // Show initial message
    Terminal::output()->write(sprintf("  ◦ %s", $this->message));

    $result = $callback();

    // Clear line and show result
    if (Terminal::supportsCursor()) {
        Terminal::output()->write("\r" . str_repeat(' ', strlen($this->message) + 10) . "\r");
    } else {
        Terminal::output()->writeln('');
    }

    if ($result !== false) {
        Terminal::output()->writeln(sprintf("  ⬡ %s", $this->message));
    } else {
        Terminal::output()->writeln(sprintf("  ✗ %s", $this->message));
    }

    return $result;
}

private function runInteractive(callable $callback): mixed
{
    return $this->runCallBack($callback);
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/UI/Widget/Spinner/SpinnerTest.php`
Expected: PASS (all 3 tests)

**Step 5: Run PHPStan**

Run: `vendor/bin/phpstan analyse src/UI/Widget/Spinner/Spinner.php --level=10`
Expected: No errors

**Step 6: Commit**

```bash
git add src/UI/Widget/Spinner/Spinner.php tests/Unit/UI/Widget/Spinner/SpinnerTest.php
git commit -m "feat: add Spinner headless mode with static output"
```

---

## Task 6: Create InteractsWithPrompts Test Trait

**Files:**
- Create: `tests/Support/InteractsWithPrompts.php`

**Step 1: Create the trait**

Create `tests/Support/InteractsWithPrompts.php`:

```php
<?php

declare(strict_types=1);

// ABOUTME: Trait for tests that need to interact with prompts.
// ABOUTME: Provides helpers to set preset responses in headless mode.

namespace Seaman\Tests\Support;

use Seaman\UI\HeadlessMode;

trait InteractsWithPrompts
{
    /**
     * Set specific responses for prompts.
     *
     * @param array<string, mixed> $responses
     */
    protected function setPromptResponses(array $responses): void
    {
        HeadlessMode::enable();
        HeadlessMode::preset($responses);
    }

    /**
     * Use default values for all prompts.
     */
    protected function useDefaults(): void
    {
        HeadlessMode::enable();
    }

    /**
     * Reset headless mode (call in afterEach).
     */
    protected function resetPrompts(): void
    {
        HeadlessMode::reset();
    }
}
```

**Step 2: Run PHPStan**

Run: `vendor/bin/phpstan analyse tests/Support/InteractsWithPrompts.php --level=10`
Expected: No errors

**Step 3: Commit**

```bash
git add tests/Support/InteractsWithPrompts.php
git commit -m "feat: add InteractsWithPrompts test trait"
```

---

## Task 7: Update InitializationWizard to Use Prompts Wrapper

**Files:**
- Modify: `src/Service/InitializationWizard.php`

**Step 1: Update imports and usage**

In `src/Service/InitializationWizard.php`, replace:

```php
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
```

With:
```php
use Seaman\UI\Prompts;
```

Then replace all calls:
- `confirm(...)` → `Prompts::confirm(...)`
- `select(...)` → `Prompts::select(...)`
- `multiselect(...)` → `Prompts::multiselect(...)`
- `info(...)` → `Prompts::info(...)`

**Step 2: Run existing tests**

Run: `vendor/bin/pest tests/Integration/Command/InitCommandTest.php`
Expected: PASS

**Step 3: Run PHPStan**

Run: `vendor/bin/phpstan analyse src/Service/InitializationWizard.php --level=10`
Expected: No errors

**Step 4: Commit**

```bash
git add src/Service/InitializationWizard.php
git commit -m "refactor: use Prompts wrapper in InitializationWizard"
```

---

## Task 8: Update PortAllocator to Use Prompts Wrapper

**Files:**
- Modify: `src/Service/PortAllocator.php`

**Step 1: Update imports and usage**

In `src/Service/PortAllocator.php`, replace:

```php
use function Laravel\Prompts\confirm;
```

With:
```php
use Seaman\UI\Prompts;
```

Then replace:
- `confirm(...)` → `Prompts::confirm(...)`

**Step 2: Run PHPStan**

Run: `vendor/bin/phpstan analyse src/Service/PortAllocator.php --level=10`
Expected: No errors

**Step 3: Commit**

```bash
git add src/Service/PortAllocator.php
git commit -m "refactor: use Prompts wrapper in PortAllocator"
```

---

## Task 9: Update Remaining Commands (Batch)

**Files to modify:**
- `src/Command/InitCommand.php`
- `src/Command/DestroyCommand.php`
- `src/Command/ServiceAddCommand.php`
- `src/Command/ServiceRemoveCommand.php`
- `src/Command/AbstractServiceCommand.php`
- `src/Command/DevContainerGenerateCommand.php`
- `src/Command/ProxyConfigureDnsCommand.php`
- `src/Command/StatusCommand.php`
- `src/Command/ServiceListCommand.php`
- `src/Command/Database/DbRestoreCommand.php`
- `src/Command/Database/DbShellCommand.php`
- `src/Command/Concern/SelectsDatabaseService.php`

**Step 1: For each file, apply the same pattern**

Replace Laravel Prompts imports:
```php
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\table;
```

With:
```php
use Seaman\UI\Prompts;
```

And update all calls to use `Prompts::` prefix.

**Step 2: Run all tests**

Run: `vendor/bin/pest`
Expected: All tests PASS

**Step 3: Run PHPStan on all modified files**

Run: `vendor/bin/phpstan analyse src/Command/ --level=10`
Expected: No errors

**Step 4: Commit**

```bash
git add src/Command/
git commit -m "refactor: use Prompts wrapper in all commands"
```

---

## Task 10: Write Integration Test for InitCommand

**Files:**
- Modify: `tests/Integration/Command/InitCommandTest.php`

**Step 1: Add integration test with preset responses**

Add to `tests/Integration/Command/InitCommandTest.php`:

```php
use Seaman\UI\HeadlessMode;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function (): void {
    HeadlessMode::reset();
    $this->tempDir = sys_get_temp_dir() . '/seaman-init-test-' . uniqid();
    mkdir($this->tempDir, 0755, true);
    $this->originalDir = getcwd();
    chdir($this->tempDir);
});

afterEach(function (): void {
    HeadlessMode::reset();
    chdir($this->originalDir);
    exec('rm -rf ' . escapeshellarg($this->tempDir));
});

test('init command creates configuration with preset responses', function (): void {
    // Create minimal Symfony project structure
    file_put_contents($this->tempDir . '/composer.json', json_encode([
        'require' => ['symfony/framework-bundle' => '^7.0'],
    ]));
    mkdir($this->tempDir . '/src');

    HeadlessMode::enable();
    HeadlessMode::preset([
        'Select PHP version (default: 8.4)' => '8.4',
        'Select database (default: postgresql)' => 'mysql',
        'Select additional services' => ['redis'],
        'Do you want to enable Xdebug?' => false,
        'Use Traefik as reverse proxy?' => false,
        'Do you want to enable DevContainer support?' => false,
    ]);

    $app = new \Seaman\Application();
    $tester = new CommandTester($app->find('init'));
    $tester->execute([]);

    expect(file_exists($this->tempDir . '/.seaman/seaman.yaml'))->toBeTrue();
});
```

**Step 2: Run test**

Run: `vendor/bin/pest tests/Integration/Command/InitCommandTest.php`
Expected: PASS

**Step 3: Commit**

```bash
git add tests/Integration/Command/InitCommandTest.php
git commit -m "test: add InitCommand integration test with headless mode"
```

---

## Task 11: Run Full Test Suite and Fix Issues

**Step 1: Run all tests**

Run: `vendor/bin/pest`
Expected: All tests PASS

**Step 2: Run PHPStan on entire src/**

Run: `vendor/bin/phpstan analyse src/ --level=10`
Expected: No errors

**Step 3: Run php-cs-fixer**

Run: `vendor/bin/php-cs-fixer fix`

**Step 4: Final commit**

```bash
git add -A
git commit -m "chore: fix code style"
```

---

## Summary

This plan creates 4 new files and modifies ~18 existing files to introduce:

1. **HeadlessMode** - State management for headless/test mode
2. **Prompts** - Wrapper over Laravel Prompts with headless support
3. **HeadlessModeException** - Exception for missing defaults
4. **InteractsWithPrompts** - Test trait for command tests

The changes are backwards-compatible - all existing interactive functionality works unchanged. Tests can now preset prompt responses for full command integration testing.

---

Plan complete and saved to `docs/plans/2025-12-08-headless-ui-implementation.md`. Two execution options:

**1. Subagent-Driven (this session)** - I dispatch fresh subagent per task, review between tasks, fast iteration

**2. Parallel Session (separate)** - Open new session with executing-plans, batch execution with checkpoints

Which approach?
