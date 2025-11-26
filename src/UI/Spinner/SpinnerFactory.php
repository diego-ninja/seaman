<?php

declare(strict_types=1);

namespace Seaman\UI\Spinner;

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
        $spinner = new self(style: $style);
        $spinner->setMessage($message);

        return $spinner->callback(static function () use ($process): bool {
            if (!$process->isRunning()) {
                $process->start();
            }

            while ($process->isRunning()) {
                usleep(1000);
            }

            return $process->isSuccessful();
        });
    }

    /**
     * @throws Exception
     */
    private static function forCallable(callable $callback, string $message, ?string $style = Spinner::DEFAULT_SPINNER_STYLE): bool
    {
        $spinner = new self(style: $style);
        $spinner->setMessage($message);

        return $spinner->callback($callback);
    }
}
