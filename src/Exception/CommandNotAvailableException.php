<?php

// ABOUTME: Exception thrown when a command is not available in the current mode.
// ABOUTME: Provides helpful message about running seaman init.

declare(strict_types=1);

namespace Seaman\Exception;

use Seaman\Enum\OperatingMode;

final class CommandNotAvailableException extends SeamanException
{
    public static function forCommand(string $commandName, OperatingMode $mode): self
    {
        $message = sprintf(
            'Command "%s" is not available in %s mode.',
            $commandName,
            $mode->label(),
        );

        if ($mode !== OperatingMode::Managed) {
            $message .= "\nRun \"seaman init\" to enable all features.";
        }

        return new self($message);
    }
}
