<?php

declare(strict_types=1);

// ABOUTME: Immutable configuration root object.
// ABOUTME: Represents the complete seaman.yaml configuration.

namespace Seaman\ValueObject;

readonly class Configuration
{
    public function __construct(
        public string $version,
        public ServerConfig $server,
        public PhpConfig $php,
        public ServiceCollection $services,
        public VolumeConfig $volumes,
    ) {}
}
