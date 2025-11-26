<?php

declare(strict_types=1);

namespace Seaman\UI;

use Symfony\Component\Console\Formatter\OutputFormatterStyleInterface;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

use Symfony\Component\Console\Style\SymfonyStyle;
use function Termwind\terminal;

final class Terminal
{
    private static ?self $instance = null;

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
     * Get the console output object.
     *
     * @return ConsoleOutput The console output object.
     */
    public static function output(): ConsoleOutput
    {
        return self::getInstance()->output;
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
        for ($i = 0; $i < $lines; $i++) {
            self::output()->write("\033[1A");
            self::output()->write("\033[2K");
        }

        self::output()->write("\033[1G");
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
        fprintf($stream, "\033[?25l"); // hide cursor
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
        return new SymfonyStyle(Terminal::input(), Terminal::output());
    }

    private function __construct(
        private readonly ConsoleOutput $output,
        private readonly ?StreamableInputInterface $input = null,
    ) {}
}
