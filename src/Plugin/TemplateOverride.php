<?php

// ABOUTME: Represents a template override from a plugin.
// ABOUTME: Maps core template path to plugin replacement path.

declare(strict_types=1);

namespace Seaman\Plugin;

final readonly class TemplateOverride
{
    public function __construct(
        public string $originalTemplate,
        public string $overridePath,
    ) {}
}
