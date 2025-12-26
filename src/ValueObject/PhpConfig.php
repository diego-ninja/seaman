<?php

declare(strict_types=1);

// ABOUTME: PHP configuration value object.
// ABOUTME: Validates PHP version and manages server and Xdebug configuration.

namespace Seaman\ValueObject;

use InvalidArgumentException;
use Seaman\Enum\PhpVersion;
use Seaman\Enum\ServerType;

final readonly class PhpConfig
{
    public function __construct(
        public PhpVersion $version,
        public XdebugConfig $xdebug,
        public ServerType $server = ServerType::SymfonyServer,
    ) {
        if (!PhpVersion::isSupported($this->version)) {
            throw new InvalidArgumentException(
                "Unsupported PHP version: {$version->value}. Must be one of: " . implode(', ', array_map(static fn(PhpVersion $version): string => $version->value, PhpVersion::supported())),
            );
        }
    }
}
