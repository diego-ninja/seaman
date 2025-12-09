<?php

// ABOUTME: Exception thrown when configuration is invalid.
// ABOUTME: Contains validation error message and optional context information.

declare(strict_types=1);

namespace Seaman\Exception;

use RuntimeException;

final class InvalidConfigurationException extends RuntimeException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message,
        private readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
