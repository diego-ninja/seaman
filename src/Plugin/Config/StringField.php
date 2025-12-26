<?php

// ABOUTME: Represents a string configuration field.
// ABOUTME: Supports enum constraints and secret flag for passwords.

declare(strict_types=1);

namespace Seaman\Plugin\Config;

final readonly class StringField implements FieldInterface
{
    /**
     * @param list<string>|null $enum
     */
    public function __construct(
        private string $name,
        private ?string $default,
        private bool $nullable,
        private ?array $enum,
        private string $label,
        private string $description,
        private bool $isSecret,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getDefault(): ?string
    {
        return $this->default;
    }

    public function getType(): string
    {
        return 'string';
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * @return list<string>|null
     */
    public function getEnum(): ?array
    {
        return $this->enum;
    }

    public function getMetadata(): FieldMetadata
    {
        return new FieldMetadata(
            label: $this->label,
            description: $this->description,
            isSecret: $this->isSecret,
        );
    }
}
