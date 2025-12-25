<?php

// ABOUTME: Tests for ConfigDataExtractor trait.
// ABOUTME: Validates type-safe data extraction with defaults.

declare(strict_types=1);

use Seaman\Exception\InvalidConfigurationException;
use Seaman\Service\ConfigParser\ConfigDataExtractor;

// Create a test class that uses the trait
final class TestExtractor
{
    use ConfigDataExtractor;

    /**
     * @param array<string, mixed> $data
     */
    public function testGetString(array $data, string $key, string $default): string
    {
        return $this->getString($data, $key, $default);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function testGetBool(array $data, string $key, bool $default): bool
    {
        return $this->getBool($data, $key, $default);
    }

    /**
     * @param array<string, mixed> $data
     */
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

beforeEach(function (): void {
    $this->extractor = new TestExtractor();
});

describe('getString', function (): void {
    test('returns value when string exists', function (): void {
        $data = ['name' => 'test'];
        expect($this->extractor->testGetString($data, 'name', 'default'))->toBe('test');
    });

    test('returns default when key missing', function (): void {
        $data = [];
        expect($this->extractor->testGetString($data, 'name', 'default'))->toBe('default');
    });

    test('returns default when value is not string', function (): void {
        $data = ['name' => 123];
        expect($this->extractor->testGetString($data, 'name', 'default'))->toBe('default');
    });

    test('returns default when value is null', function (): void {
        $data = ['name' => null];
        expect($this->extractor->testGetString($data, 'name', 'default'))->toBe('default');
    });
});

describe('getBool', function (): void {
    test('returns true when boolean true exists', function (): void {
        $data = ['enabled' => true];
        expect($this->extractor->testGetBool($data, 'enabled', false))->toBeTrue();
    });

    test('returns false when boolean false exists', function (): void {
        $data = ['enabled' => false];
        expect($this->extractor->testGetBool($data, 'enabled', true))->toBeFalse();
    });

    test('returns default when key missing', function (): void {
        $data = [];
        expect($this->extractor->testGetBool($data, 'enabled', true))->toBeTrue();
    });

    test('returns default when value is not boolean', function (): void {
        $data = ['enabled' => 'yes'];
        expect($this->extractor->testGetBool($data, 'enabled', false))->toBeFalse();
    });
});

describe('getInt', function (): void {
    test('returns value when int exists', function (): void {
        $data = ['port' => 8080];
        expect($this->extractor->testGetInt($data, 'port', 0))->toBe(8080);
    });

    test('returns default when key missing', function (): void {
        $data = [];
        expect($this->extractor->testGetInt($data, 'port', 3306))->toBe(3306);
    });

    test('returns default when value is not int', function (): void {
        $data = ['port' => '8080'];
        expect($this->extractor->testGetInt($data, 'port', 0))->toBe(0);
    });
});

describe('getArray', function (): void {
    test('returns value when array exists', function (): void {
        $data = ['items' => ['a', 'b', 'c']];
        expect($this->extractor->testGetArray($data, 'items'))->toBe(['a', 'b', 'c']);
    });

    test('returns empty array when key missing', function (): void {
        $data = [];
        expect($this->extractor->testGetArray($data, 'items'))->toBe([]);
    });

    test('returns default when key missing and default provided', function (): void {
        $data = [];
        expect($this->extractor->testGetArray($data, 'items', ['default']))->toBe(['default']);
    });

    test('returns default when value is not array', function (): void {
        $data = ['items' => 'not-array'];
        expect($this->extractor->testGetArray($data, 'items', ['fallback']))->toBe(['fallback']);
    });
});

describe('requireArray', function (): void {
    test('returns array when valid', function (): void {
        $data = ['config' => ['key' => 'value']];
        expect($this->extractor->testRequireArray($data, 'config', 'Error'))->toBe(['key' => 'value']);
    });

    test('returns empty array when key missing', function (): void {
        $data = [];
        expect($this->extractor->testRequireArray($data, 'config', 'Error'))->toBe([]);
    });

    test('throws exception when value is not array', function (): void {
        $data = ['config' => 'invalid'];
        expect(fn() => $this->extractor->testRequireArray($data, 'config', 'Config must be array'))
            ->toThrow(InvalidConfigurationException::class, 'Config must be array');
    });
});
