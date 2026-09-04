<?php

declare(strict_types=1);

// ABOUTME: Test fixture exposing the protected configuration extraction helpers.
// ABOUTME: Provides typed wrappers for ConfigDataExtractor unit tests.

namespace Seaman\Tests\Unit\Service\ConfigParser;

use Seaman\Service\ConfigParser\ConfigDataExtractor;

final class TestExtractor
{
    use ConfigDataExtractor;

    /** @param array<string, mixed> $data */
    public function testGetString(array $data, string $key, string $default): string
    {
        return $this->getString($data, $key, $default);
    }

    /** @param array<string, mixed> $data */
    public function testGetBool(array $data, string $key, bool $default): bool
    {
        return $this->getBool($data, $key, $default);
    }

    /** @param array<string, mixed> $data */
    public function testGetInt(array $data, string $key, int $default): int
    {
        return $this->getInt($data, $key, $default);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<mixed> $default
     * @return array<mixed>
     */
    public function testGetArray(array $data, string $key, array $default = []): array
    {
        return $this->getArray($data, $key, $default);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function testRequireArray(array $data, string $key, string $message): array
    {
        return $this->requireArray($data, $key, $message);
    }
}
