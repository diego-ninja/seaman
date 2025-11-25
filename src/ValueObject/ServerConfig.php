<?php

declare(strict_types=1);

// ABOUTME: Immutable server configuration value object.
// ABOUTME: Validates server type and port constraints.

namespace Seaman\ValueObject;

readonly class ServerConfig
{
    private const array VALID_TYPES = ['symfony', 'nginx-fpm', 'frankenphp'];
    private const int MIN_PORT = 1024;
    private const int MAX_PORT = 65535;

    public function __construct(
        public string $type,
        public int $port,
    ) {
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException(
                "Invalid server type: {$type}. Must be one of: " . implode(', ', self::VALID_TYPES),
            );
        }

        if ($port < self::MIN_PORT || $port > self::MAX_PORT) {
            throw new \InvalidArgumentException(
                "Invalid port: {$port}. Must be between " . self::MIN_PORT . " and " . self::MAX_PORT,
            );
        }
    }
}
