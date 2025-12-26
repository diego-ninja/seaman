<?php

// ABOUTME: Attribute to mark a method as providing a template override.
// ABOUTME: Method must return the absolute path to the replacement template.

declare(strict_types=1);

namespace Seaman\Plugin\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class OverridesTemplate
{
    public function __construct(
        public string $template,
    ) {}
}
