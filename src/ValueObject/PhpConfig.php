<?php

declare(strict_types=1);

// ABOUTME: PHP configuration value object.
// ABOUTME: Validates PHP version and manages extensions.

namespace Seaman\ValueObject;

readonly class PhpConfig
{
    private const array SUPPORTED_VERSIONS = ['8.2', '8.3', '8.4'];

    /**
     * @param list<string> $extensions
     */
    public function __construct(
        public string $version,
        public array $extensions,
        public XdebugConfig $xdebug,
    ) {
        if (!in_array($version, self::SUPPORTED_VERSIONS, true)) {
            throw new \InvalidArgumentException(
                "Unsupported PHP version: {$version}. Must be one of: " . implode(', ', self::SUPPORTED_VERSIONS),
            );
        }
    }
}
