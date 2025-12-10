<?php

declare(strict_types=1);

// ABOUTME: Factory for creating spinner animations with processes or callables.
// ABOUTME: Provides convenient static methods for progress indication.

namespace Seaman\UI\Widget\Spinner;

use Exception;
use Symfony\Component\Process\Process;

class SpinnerFactory extends Spinner
{
    /**
     * @throws Exception
     */
    public static function for(Process|callable $callable, string $message, ?string $style = Spinner::DEFAULT_SPINNER_STYLE): bool
    {
        return $callable instanceof Process
            ? self::forProcess($callable, $message, $style)
            : self::forCallable($callable, $message, $style);
    }

    /**
     * @throws Exception
     */
    private static function forProcess(Process $process, string $message, ?string $style = Spinner::DEFAULT_SPINNER_STYLE): bool
    {
        $spinner = new self(style: $style ?? Spinner::DEFAULT_SPINNER_STYLE);
        $spinner->setMessage($message);

        $result = $spinner->callback(static function () use ($process): bool {
            if (!$process->isRunning()) {
                $process->start();
            }

            while ($process->isRunning()) {
                usleep(1000);
            }

            return $process->isSuccessful();
        });

        return is_bool($result) ? $result : false;
    }

    /**
     * @throws Exception
     */
    private static function forCallable(callable $callback, string $message, ?string $style = Spinner::DEFAULT_SPINNER_STYLE): bool
    {
        $spinner = new self(style: $style ?? Spinner::DEFAULT_SPINNER_STYLE);
        $spinner->setMessage($message);

        $result = $spinner->callback($callback);

        return is_bool($result) ? $result : false;
    }
}
