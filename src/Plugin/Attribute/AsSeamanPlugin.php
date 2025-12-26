<?php

// ABOUTME: Attribute to mark a class as a Seaman plugin.
// ABOUTME: Provides plugin identity metadata for registration.

declare(strict_types=1);

namespace Seaman\Plugin\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsSeamanPlugin
{
    /**
     * @param string $name Unique plugin identifier
     * @param string $version Semantic version
     * @param string $description Human-readable description
     * @param list<string> $requires Dependencies (e.g., ['seaman/core:^1.0'])
     */
    public function __construct(
        public string $name,
        public string $version = '1.0.0',
        public string $description = '',
        public array $requires = [],
    ) {}
}
