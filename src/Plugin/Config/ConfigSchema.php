<?php

// ABOUTME: Defines and validates plugin configuration schema.
// ABOUTME: Supports integer, string, and boolean fields with constraints and UI metadata.

declare(strict_types=1);

namespace Seaman\Plugin\Config;

final class ConfigSchema
{
    /** @var array<string, array{type: string, default: mixed, nullable: bool, min?: int|null, max?: int|null, enum?: list<string>|null, label: string, description: string, isSecret: bool}> */
    private array $fields = [];

    private ?string $currentField = null;

    public static function create(): self
    {
        return new self();
    }

    public function integer(
        string $name,
        int $default,
        ?int $min = null,
        ?int $max = null,
    ): self {
        $this->fields[$name] = [
            'type' => 'integer',
            'default' => $default,
            'nullable' => false,
            'min' => $min,
            'max' => $max,
            'label' => $this->generateLabelFromName($name),
            'description' => '',
            'isSecret' => false,
        ];
        $this->currentField = $name;

        return $this;
    }

    public function string(
        string $name,
        ?string $default = null,
        bool $nullable = false,
    ): self {
        $this->fields[$name] = [
            'type' => 'string',
            'default' => $default,
            'nullable' => $nullable,
            'enum' => null,
            'label' => $this->generateLabelFromName($name),
            'description' => '',
            'isSecret' => false,
        ];
        $this->currentField = $name;

        return $this;
    }

    public function boolean(
        string $name,
        bool $default = false,
    ): self {
        $this->fields[$name] = [
            'type' => 'boolean',
            'default' => $default,
            'nullable' => false,
            'label' => $this->generateLabelFromName($name),
            'description' => '',
            'isSecret' => false,
        ];
        $this->currentField = $name;

        return $this;
    }

    public function label(string $label): self
    {
        if ($this->currentField === null) {
            throw new \LogicException('Cannot set label without first defining a field');
        }

        $this->fields[$this->currentField]['label'] = $label;

        return $this;
    }

    public function description(string $description): self
    {
        if ($this->currentField === null) {
            throw new \LogicException('Cannot set description without first defining a field');
        }

        $this->fields[$this->currentField]['description'] = $description;

        return $this;
    }

    public function secret(): self
    {
        if ($this->currentField === null) {
            throw new \LogicException('Cannot set secret without first defining a field');
        }

        if ($this->fields[$this->currentField]['type'] !== 'string') {
            throw new \LogicException('secret() can only be used on string fields');
        }

        $this->fields[$this->currentField]['isSecret'] = true;

        return $this;
    }

    /**
     * @param list<string> $values
     */
    public function enum(array $values): self
    {
        if ($this->currentField === null) {
            throw new \LogicException('Cannot set enum without first defining a field');
        }

        if ($this->fields[$this->currentField]['type'] !== 'string') {
            throw new \LogicException('enum() can only be used on string fields');
        }

        $this->fields[$this->currentField]['enum'] = $values;

        return $this;
    }

    /**
     * @return array<string, FieldInterface>
     */
    public function getFields(): array
    {
        $fieldObjects = [];

        foreach ($this->fields as $name => $definition) {
            $fieldObjects[$name] = $this->createFieldObject($name, $definition);
        }

        return $fieldObjects;
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     * @throws ConfigValidationException
     */
    public function validate(array $values): array
    {
        $validated = [];

        // Check for unknown fields
        foreach (array_keys($values) as $key) {
            if (!isset($this->fields[$key])) {
                throw ConfigValidationException::unknownField($key);
            }
        }

        // Validate and apply defaults
        foreach ($this->fields as $name => $definition) {
            $value = $values[$name] ?? $definition['default'];
            $validated[$name] = $this->validateField($name, $value, $definition);
        }

        return $validated;
    }

    /**
     * @param array{type: string, default: mixed, nullable: bool, min?: int|null, max?: int|null, enum?: list<string>|null, label: string, description: string, isSecret: bool} $definition
     */
    private function createFieldObject(string $name, array $definition): FieldInterface
    {
        return match ($definition['type']) {
            'string' => new StringField(
                name: $name,
                default: is_string($definition['default']) ? $definition['default'] : null,
                nullable: $definition['nullable'],
                enum: $definition['enum'] ?? null,
                label: $definition['label'],
                description: $definition['description'],
                isSecret: $definition['isSecret'],
            ),
            'integer' => new IntegerField(
                name: $name,
                default: is_int($definition['default']) ? $definition['default'] : 0,
                min: $definition['min'] ?? null,
                max: $definition['max'] ?? null,
                label: $definition['label'],
                description: $definition['description'],
            ),
            'boolean' => new BooleanField(
                name: $name,
                default: is_bool($definition['default']) ? $definition['default'] : false,
                label: $definition['label'],
                description: $definition['description'],
            ),
            default => throw new \LogicException("Unknown field type: {$definition['type']}"),
        };
    }

    /**
     * @param array{type: string, default: mixed, nullable: bool, min?: int|null, max?: int|null, enum?: list<string>|null, label: string, description: string, isSecret: bool} $definition
     */
    private function validateField(string $name, mixed $value, array $definition): mixed
    {
        if ($value === null) {
            if (!$definition['nullable']) {
                throw ConfigValidationException::invalidValue($name, 'cannot be null');
            }

            return null;
        }

        return match ($definition['type']) {
            'integer' => $this->validateInteger($name, $value, $definition),
            'string' => $this->validateString($name, $value, $definition),
            'boolean' => $this->validateBoolean($name, $value),
            default => $value,
        };
    }

    /**
     * @param array{type: string, default: mixed, nullable: bool, min?: int|null, max?: int|null, enum?: list<string>|null, label: string, description: string, isSecret: bool} $definition
     */
    private function validateInteger(string $name, mixed $value, array $definition): int
    {
        if (!is_int($value)) {
            throw ConfigValidationException::invalidValue($name, 'must be an integer');
        }

        if (isset($definition['min']) && $value < $definition['min']) {
            throw ConfigValidationException::invalidValue($name, "must be at least {$definition['min']}");
        }

        if (isset($definition['max']) && $value > $definition['max']) {
            throw ConfigValidationException::invalidValue($name, "must be at most {$definition['max']}");
        }

        return $value;
    }

    /**
     * @param array{type: string, default: mixed, nullable: bool, min?: int|null, max?: int|null, enum?: list<string>|null, label: string, description: string, isSecret: bool} $definition
     */
    private function validateString(string $name, mixed $value, array $definition): string
    {
        if (!is_string($value)) {
            throw ConfigValidationException::invalidValue($name, 'must be a string');
        }

        if (isset($definition['enum']) && !in_array($value, $definition['enum'], true)) {
            $allowed = implode(', ', $definition['enum']);
            throw ConfigValidationException::invalidValue($name, "must be one of: {$allowed}");
        }

        return $value;
    }

    private function validateBoolean(string $name, mixed $value): bool
    {
        if (!is_bool($value)) {
            throw ConfigValidationException::invalidValue($name, 'must be a boolean');
        }

        return $value;
    }

    private function generateLabelFromName(string $name): string
    {
        return ucwords(str_replace('_', ' ', $name));
    }
}
