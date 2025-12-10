<?php

declare(strict_types=1);

namespace Seaman\UI;

use Seaman\UI\HeadlessMode;
use Symfony\Component\Console\Formatter\OutputFormatterStyleInterface;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Termwind\terminal;

final class Terminal
{
    private static ?self $instance = null;
    private static ?bool $supportsAnsi = null;
    private static ?OutputInterface $currentOutput = null;

    /**
     * Get the singleton instance of the Terminal class.
     *
     * @return self The Terminal instance.
     */
    public static function getInstance(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self(
                new ConsoleOutput(),
                new ArgvInput(),
            );
        }
        return self::$instance;
    }

    /**
     * Set a custom output interface for the terminal.
     * This allows commands to inject their own output for testing.
     *
     * @param OutputInterface $output The output interface to use.
     */
    public static function setOutput(OutputInterface $output): void
    {
        self::$currentOutput = $output;
    }

    /**
     * Reset the output interface to the default singleton output.
     */
    public static function resetOutput(): void
    {
        self::$currentOutput = null;
    }

    /**
     * Get the console output object.
     *
     * @return OutputInterface The console output object.
     */
    public static function output(): OutputInterface
    {
        return self::$currentOutput ?? self::getInstance()->output;
    }

    /**
     * Get the streamable input object.
     *
     * @return StreamableInputInterface|null The streamable input object.
     */
    public static function input(): ?StreamableInputInterface
    {
        return self::getInstance()->input;
    }

    /**
     * Clear a specified number of lines from the terminal.
     *
     * @param int $lines The number of lines to clear.
     */
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

    /**
     * Reset the terminal using Termwind.
     */
    public static function reset(): void
    {
        terminal()->clear();
    }

    /**
     * Render a message using the terminal's formatter.
     *
     * @param string $message The message to render.
     *
     * @return string|null The rendered message.
     */
    public static function render(string $message): ?string
    {
        return self::output()->getFormatter()->format($message);
    }

    /**
     * Get the width of the terminal.
     *
     * @return int The width of the terminal.
     */
    public static function width(): int
    {
        return terminal()->width();
    }

    public static function info(string $message): void
    {
        self::output()->writeln(sprintf('  <fg=gray>%s</>', $message));
    }

    public static function success(string $message): void
    {
        $symbol = self::supportsAnsi()
            ? '<fg=bright-green>✓</>'
            : '✓';

        self::output()->writeln(sprintf(
            "%s%s %s",
            str_repeat(' ', 2),
            $symbol,
            $message,
        ));
    }

    public static function error(string $message): void
    {
        $symbol = self::supportsAnsi()
            ? '<fg=bright-red>✗</>'
            : '✗';
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

    /**
     * Get a color style by name from the terminal formatter.
     *
     * @param string $colorName The name of the color style.
     *
     * @return OutputFormatterStyleInterface The color style.
     */
    public static function color(string $colorName): OutputFormatterStyleInterface
    {
        return self::output()->getFormatter()->getStyle($colorName);
    }

    /**
     * Hide the cursor.
     * @param resource $stream
     */
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

    /**
     * Restore the cursor to its original position.
     */
    public static function restoreCursor(): void
    {
        self::output()->write("\033[?25h");
    }

    /**
     * Get the stream associated with the terminal input.
     *
     * @return resource The input stream or STDIN if not available.
     */
    public static function stream(): mixed
    {
        return self::input()?->getStream() ?: STDIN;
    }

    public static function style(): SymfonyStyle
    {
        return new SymfonyStyle(Terminal::input() ?? new ArgvInput(), Terminal::output());
    }

    private function __construct(
        private readonly ConsoleOutput $output,
        private readonly ?StreamableInputInterface $input = null,
    ) {}
}
