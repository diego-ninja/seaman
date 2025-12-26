<?php

// ABOUTME: Provides typed access to validated plugin configuration.
// ABOUTME: Immutable value object wrapping configuration array.

declare(strict_types=1);

namespace Seaman\Plugin\Config;

final readonly class PluginConfig
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(
        private array $values,
    ) {}

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }
}
