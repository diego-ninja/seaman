<?php

// ABOUTME: Interface for configuration field types.
// ABOUTME: Defines contract for field name, default value, and metadata access.

declare(strict_types=1);

namespace Seaman\Plugin\Config;

interface FieldInterface
{
    public function getName(): string;

    public function getDefault(): mixed;

    public function getType(): string;

    public function getMetadata(): FieldMetadata;
}
