<?php

// ABOUTME: Exception thrown when a command is used in an unsupported mode.
// ABOUTME: Contains command name and current mode information.

declare(strict_types=1);

namespace Seaman\Exception;

use RuntimeException;
use Seaman\Enum\OperatingMode;

final class UnsupportedModeException extends RuntimeException
{
    private function __construct(
        string $message,
        private readonly string $commandName,
        private readonly OperatingMode $mode,
    ) {
        parent::__construct($message);
    }

    public static function forCommand(string $commandName, OperatingMode $mode): self
    {
        $message = sprintf(
            'Command "%s" is not supported in %s mode.',
            $commandName,
            $mode->label(),
        );

        return new self($message, $commandName, $mode);
    }

    public function getCommandName(): string
    {
        return $this->commandName;
    }

    public function getMode(): OperatingMode
    {
        return $this->mode;
    }
}
