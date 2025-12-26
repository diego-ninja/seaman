<?php

// ABOUTME: Attribute to mark a method as providing a Docker service.
// ABOUTME: Method must return a ServiceDefinition instance.

declare(strict_types=1);

namespace Seaman\Plugin\Attribute;

use Attribute;
use Seaman\Enum\ServiceCategory;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ProvidesService
{
    public function __construct(
        public string $name,
        public ServiceCategory $category = ServiceCategory::Misc,
    ) {}
}
