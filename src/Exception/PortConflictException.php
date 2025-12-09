<?php

// ABOUTME: Exception thrown when port conflicts are detected.
// ABOUTME: Contains port number, service name, and conflicting process information.

declare(strict_types=1);

namespace Seaman\Exception;

use RuntimeException;

final class PortConflictException extends RuntimeException
{
    private function __construct(
        string $message,
        private readonly int $port,
        private readonly string $service,
        private readonly string $conflictingProcess,
    ) {
        parent::__construct($message);
    }

    public static function forPort(int $port, string $service, string $conflictingProcess): self
    {
        $message = sprintf(
            'Port %d required by service "%s" is already in use by "%s".',
            $port,
            $service,
            $conflictingProcess,
        );

        return new self($message, $port, $service, $conflictingProcess);
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getService(): string
    {
        return $this->service;
    }

    public function getConflictingProcess(): string
    {
        return $this->conflictingProcess;
    }
}
