<?php

// ABOUTME: Attribute to mark a method as providing a CLI command.
// ABOUTME: Method must return a Symfony Console Command instance.

declare(strict_types=1);

namespace Seaman\Plugin\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ProvidesCommand {}
