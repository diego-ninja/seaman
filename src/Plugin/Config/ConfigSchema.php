<?php

// ABOUTME: Defines and validates plugin configuration schema.
// ABOUTME: Supports integer, string, and boolean fields with constraints.

declare(strict_types=1);

namespace Seaman\Plugin\Config;

final class ConfigSchema
{
    /** @var array<string, array{type: string, default: mixed, nullable: bool, min?: int|null, max?: int|null}> */
    private array $fields = [];

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
        ];
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
        ];
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
        ];
        return $this;
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
     * @param array{type: string, default: mixed, nullable: bool, min?: int|null, max?: int|null} $definition
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
            'string' => $this->validateString($name, $value),
            'boolean' => $this->validateBoolean($name, $value),
            default => $value,
        };
    }

    /**
     * @param array{type: string, default: mixed, nullable: bool, min?: int|null, max?: int|null} $definition
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

    private function validateString(string $name, mixed $value): string
    {
        if (!is_string($value)) {
            throw ConfigValidationException::invalidValue($name, 'must be a string');
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
}
