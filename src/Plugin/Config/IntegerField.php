<?php

// ABOUTME: Represents an integer configuration field.
// ABOUTME: Supports min/max constraints for value validation.

declare(strict_types=1);

namespace Seaman\Plugin\Config;

final readonly class IntegerField implements FieldInterface
{
    public function __construct(
        private string $name,
        private int $default,
        private ?int $min,
        private ?int $max,
        private string $label,
        private string $description,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getDefault(): int
    {
        return $this->default;
    }

    public function getType(): string
    {
        return 'integer';
    }

    public function getMin(): ?int
    {
        return $this->min;
    }

    public function getMax(): ?int
    {
        return $this->max;
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
