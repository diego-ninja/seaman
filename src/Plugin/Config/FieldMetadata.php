<?php

// ABOUTME: Value object storing UI metadata for configuration fields.
// ABOUTME: Holds label, description, and secret flag for form rendering.

declare(strict_types=1);

namespace Seaman\Plugin\Config;

final readonly class FieldMetadata
{
    public function __construct(
        public string $label,
        public string $description = '',
        public bool $isSecret = false,
    ) {}
}
