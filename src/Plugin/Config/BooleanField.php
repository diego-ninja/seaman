<?php

// ABOUTME: Represents a boolean configuration field.
// ABOUTME: Simple true/false toggle with default value.

declare(strict_types=1);

namespace Seaman\Plugin\Config;

final readonly class BooleanField implements FieldInterface
{
    public function __construct(
        private string $name,
        private bool $default,
        private string $label,
        private string $description,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getDefault(): bool
    {
        return $this->default;
    }

    public function getType(): string
    {
        return 'boolean';
    }

    public function getMetadata(): FieldMetadata
    {
        return new FieldMetadata(
            label: $this->label,
            description: $this->description,
            isSecret: false,
        );
    }
}
