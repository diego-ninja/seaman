# Headless UI System for Integration Tests

## Overview

This design introduces an abstraction layer over interactive UI elements (Laravel Prompts, Spinner, Terminal) that enables:

1. **Normal execution** — Full interactive behavior in real terminals
2. **Automatic headless mode** — Detects CI/no-TTY and uses default values
3. **Test mode** — Allows pre-configuring specific responses for integration tests

## Problem Statement

Current integration tests cannot properly test commands that use:
- Laravel Prompts (`confirm()`, `select()`, `multiselect()`, etc.)
- Spinner animations (uses `pcntl_fork()` which blocks)
- ANSI escape codes for cursor manipulation

These elements block the terminal or require interactive input, making automated testing impossible.

## Design Decisions

### Mode Detection (in priority order)

1. `HeadlessMode::enable()` called explicitly → headless
2. `--interactive` flag present → forced interactive
3. `SEAMAN_HEADLESS=1` or `CI=true` → headless
4. `!stream_isatty(STDIN)` → headless
5. Default → interactive

### Behavior in Headless Mode

- **Prompts**: Use preset responses if available, otherwise use defaults. Throw exception if required prompt has no default.
- **Spinner**: Show static output (message → result) without animation or forking
- **Terminal**: Detect capabilities via `OutputInterface::isDecorated()`. Support ANSI colors where available, disable cursor manipulation.

## Components

### 1. HeadlessMode — State Management

```php
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

### 2. Prompts — Wrapper over Laravel Prompts

```php
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
                throw new HeadlessModeException(
                    "No default value for required prompt: {$label}"
                );
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
            // Simple text table for headless mode
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

### 3. HeadlessModeException

```php
namespace Seaman\Exception;

use RuntimeException;

final class HeadlessModeException extends RuntimeException
{
    public static function missingDefault(string $label): self
    {
        return new self("No default value for required prompt: {$label}");
    }
}
```

### 4. Spinner Modifications

Changes to `src/UI/Widget/Spinner/Spinner.php`:

```php
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
    Terminal::output()->write("  ◦ {$this->message}");

    $result = $callback();

    // Clear line and show result
    if (Terminal::supportsCursor()) {
        Terminal::output()->write("\r" . str_repeat(' ', strlen($this->message) + 10) . "\r");
    } else {
        Terminal::output()->writeln('');
    }

    if ($result !== false) {
        Terminal::output()->writeln("  ⬡ {$this->message}");
    } else {
        Terminal::output()->writeln("  ✗ {$this->message}");
    }

    return $result;
}

private function runInteractive(callable $callback): mixed
{
    // Existing code with pcntl_fork() and animation
    return $this->runCallBack($callback);
}
```

### 5. Terminal Modifications

Changes to `src/UI/Terminal.php`:

```php
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
    return !HeadlessMode::isHeadless() && posix_isatty(STDOUT);
}

public static function success(string $message): void
{
    $symbol = self::supportsAnsi()
        ? '<fg=bright-green>⬡</>'
        : '⬡';

    self::output()->writeln("  {$symbol} {$message}");
}

public static function error(string $message): void
{
    $symbol = self::supportsAnsi()
        ? '<fg=bright-red>⬡</>'  // No blink in any mode
        : '⬡';
    $text = self::supportsAnsi()
        ? "<fg=bright-red>{$message}</>"
        : $message;

    self::output()->writeln("  {$symbol} {$text}");
}

public static function hideCursor(mixed $stream = STDOUT): void
{
    if (!self::supportsCursor()) {
        return;  // No-op in headless
    }
    fprintf($stream, "\033[?25l");
    register_shutdown_function(static function (): void {
        self::restoreCursor();
    });
}

public static function clear(int $lines): void
{
    if (!self::supportsCursor()) {
        return;  // No-op in headless
    }
    // ... existing code
}
```

### 6. Test Support Trait

```php
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
}
```

## Files to Create

| File | Description |
|------|-------------|
| `src/UI/HeadlessMode.php` | Mode state management |
| `src/UI/Prompts.php` | Wrapper over Laravel Prompts |
| `src/Exception/HeadlessModeException.php` | Exception for prompts without default |
| `tests/Support/InteractsWithPrompts.php` | Trait for tests |

## Files to Modify

| File | Change |
|------|--------|
| `src/UI/Terminal.php` | Add `supportsAnsi()`, `supportsCursor()`, adapt methods |
| `src/UI/Widget/Spinner/Spinner.php` | Add `runHeadless()`, modify `callback()` |
| `src/Service/InitializationWizard.php` | Change imports to `Seaman\UI\Prompts` |
| `src/Service/PortAllocator.php` | Change imports to `Seaman\UI\Prompts` |
| `src/Command/InitCommand.php` | Change imports to `Seaman\UI\Prompts` |
| `src/Command/DestroyCommand.php` | Change imports to `Seaman\UI\Prompts` |
| `src/Command/ServiceAddCommand.php` | Change imports to `Seaman\UI\Prompts` |
| `src/Command/ServiceRemoveCommand.php` | Change imports to `Seaman\UI\Prompts` |
| `src/Command/AbstractServiceCommand.php` | Change imports to `Seaman\UI\Prompts` |
| `src/Command/DevContainerGenerateCommand.php` | Change imports to `Seaman\UI\Prompts` |
| `src/Command/ProxyConfigureDnsCommand.php` | Change imports to `Seaman\UI\Prompts` |
| `src/Command/StatusCommand.php` | Change imports to `Seaman\UI\Prompts` |
| `src/Command/ServiceListCommand.php` | Change imports to `Seaman\UI\Prompts` |
| `src/Command/Database/DbRestoreCommand.php` | Change imports to `Seaman\UI\Prompts` |
| `src/Command/Database/DbShellCommand.php` | Change imports to `Seaman\UI\Prompts` |
| `src/Command/Concern/SelectsDatabaseService.php` | Change imports to `Seaman\UI\Prompts` |

## Test Example

```php
use Seaman\Application;
use Seaman\UI\HeadlessMode;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    HeadlessMode::reset();
    $this->tempDir = TestHelper::createTempDir();
    $this->originalDir = getcwd();
    chdir($this->tempDir);
});

afterEach(function () {
    HeadlessMode::reset();
    chdir($this->originalDir);
    TestHelper::removeTempDir($this->tempDir);
});

test('init command creates configuration with mysql', function () {
    HeadlessMode::enable();
    HeadlessMode::preset([
        'Select PHP version' => '8.4',
        'Select database' => 'mysql',
        'Select additional services' => ['redis'],
        'Do you want to enable Xdebug?' => false,
        'Use Traefik as reverse proxy?' => false,
        'Do you want to enable DevContainer support?' => false,
    ]);

    $app = new Application();
    $tester = new CommandTester($app->find('init'));
    $tester->execute([]);

    expect($tester->getStatusCode())->toBe(0);
    expect(file_exists('.seaman/seaman.yaml'))->toBeTrue();

    $config = Yaml::parseFile('.seaman/seaman.yaml');
    expect($config['services']['mysql']['enabled'])->toBeTrue();
});

test('start command handles port conflict', function () {
    TestHelper::copyFixture('mysql-config', $this->tempDir);

    HeadlessMode::enable();
    HeadlessMode::preset([
        'Port 3306 is in use' => true,  // Accept alternative port
    ]);

    // Test with mocked PortChecker...
});
```

## Behavior by Environment

| Environment | ANSI Colors | Cursor Control | Prompts |
|-------------|-------------|----------------|---------|
| Real terminal | ✓ | ✓ | Interactive |
| GitHub Actions | ✓ | ✗ | Defaults |
| CI without colors | ✗ | ✗ | Defaults |
| Tests (headless) | ✗ | ✗ | Preset/Defaults |
