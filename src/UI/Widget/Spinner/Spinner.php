<?php

declare(strict_types=1);

// ABOUTME: Animated terminal spinner for long-running operations.
// ABOUTME: Supports multiple animation styles and handles signal interruption.

namespace Seaman\UI\Widget\Spinner;

use Exception;
use RuntimeException;
use Seaman\Signal\SignalHandler;
use Seaman\UI\HeadlessMode;
use Seaman\UI\Terminal;

class Spinner
{
    final public const string DEFAULT_SPINNER_STYLE = 'dots8Bit';
    final public const int DEFAULT_SPINNER_INTERVAL = 1000;
    final public const int DEFAULT_SPINNER_PADDING = 2;
    final public const string CLEAR_LINE = "\33[2K\r";
    final public const string RETURN_TO_LEFT = "\r";

    private int $child_pid = 0;

    /** @var array{
     *   frames: string[],
     *   interval: int
     * }|null
     */
    private ?array $spinner;

    private int $padding = self::DEFAULT_SPINNER_PADDING;
    private string $message;

    public function __construct(string $style = self::DEFAULT_SPINNER_STYLE)
    {
        $jsonContent = file_get_contents(__DIR__ . "/spinners.json");
        if ($jsonContent === false) {
            $this->spinner = null;
            return;
        }
        /** @var array<string, array{frames: list<string>, interval: int}> $spinners */
        $spinners = json_decode($jsonContent, true);
        $this->spinner = $spinners[$style] ?? null;
    }

    public function setMessage(string $message): self
    {
        $this->message = Terminal::render($message) ?? "";
        return $this;
    }

    public function setPadding(int $padding): self
    {
        $this->padding = $padding;
        return $this;
    }

    /**
     * @return array<string>
     */
    private function getSpinnerFrames(): array
    {
        return $this->spinner["frames"] ?? [];
    }

    private function loopSpinnerFrames(): void
    {
        Terminal::hideCursor();

        /** @phpstan-ignore-next-line */
        while (true) {
            foreach ($this->getSpinnerFrames() as $frame) {
                $parsed_frame = Terminal::render(
                    sprintf(
                        "%s<info>%s</info> %s%s",
                        $this->addPadding(),
                        $frame,
                        $this->message,
                        self::RETURN_TO_LEFT,
                    ),
                ) ?? "";

                Terminal::output()->write($parsed_frame);
                $interval = $this->spinner["interval"] ?? self::DEFAULT_SPINNER_INTERVAL;
                usleep($interval * self::DEFAULT_SPINNER_INTERVAL);
            }
        }
    }

    private function addPadding(): string
    {
        return str_repeat(' ', $this->padding);
    }

    private function reset(): void
    {
        Terminal::output()->write(self::CLEAR_LINE);
        Terminal::restoreCursor();
    }

    private function keyboardInterrupts(): void
    {
        // Keyboard interrupts. E.g. ctrl-c
        // Exit both parent and child process
        // They are both running the same code

        $keyboard_interrupts = function (int $signal): never {
            posix_kill($this->child_pid, SIGTERM);
            $this->reset();
            exit($signal);
        };

        pcntl_signal(SIGINT, $keyboard_interrupts);
        pcntl_signal(SIGTSTP, $keyboard_interrupts);
        pcntl_signal(SIGQUIT, $keyboard_interrupts);
        pcntl_async_signals(true);
    }

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
            Terminal::output()->writeln(sprintf("  ✓ %s", $this->message));
        } else {
            Terminal::output()->writeln(sprintf("  ✗ %s", $this->message));
        }

        return $result;
    }

    private function runInteractive(callable $callback): mixed
    {
        return $this->runCallBack($callback);
    }

    private function runCallBack(callable $callback): mixed
    {
        $child_pid = pcntl_fork();
        if ($child_pid === -1) {
            throw new RuntimeException('Could not fork process');
        }

        if ($child_pid !== 0) {
            $this->keyboardInterrupts();
            $this->child_pid = $child_pid;
            $res             = $callback();
            posix_kill($child_pid, SIGTERM);

            if ($res !== false) {
                $this->success();
            } else {
                $this->failure();
            }

            return $res;
        }

        $this->loopSpinnerFrames();

        SignalHandler::restore();

        return null;
    }

    private function success(): void
    {
        $this->reset();
        Terminal::output()->writeln(
            sprintf(
                "%s<fg=bright-green>%s</> %s",
                $this->addPadding(),
                '✓',
                $this->message,
            ),
        );
    }

    private function failure(): void
    {
        $this->reset();
        Terminal::output()->writeln(
            sprintf(
                "%s<fg=bright-red;options=blink>%s</> %s",
                $this->addPadding(),
                '✗',
                $this->message,
            ),
        );
    }
}
