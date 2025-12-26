<?php

// ABOUTME: Tests for FileReader service.
// ABOUTME: Validates file reading operations and error handling.

declare(strict_types=1);

use Seaman\Exception\FileNotFoundException;
use Seaman\Exception\FileOperationException;
use Seaman\Exception\YamlParseException;
use Seaman\Service\FileReader;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir() . '/seaman-file-reader-test-' . uniqid();
    mkdir($this->tempDir, 0755, true);
    $this->reader = new FileReader();
});

afterEach(function (): void {
    $files = glob($this->tempDir . '/*');
    if ($files !== false) {
        foreach ($files as $file) {
            unlink($file);
        }
    }
    rmdir($this->tempDir);
});

test('reads file contents', function (): void {
    $path = $this->tempDir . '/test.txt';
    file_put_contents($path, 'Hello World');

    $content = $this->reader->read($path);

    expect($content)->toBe('Hello World');
});

test('throws FileNotFoundException when file does not exist', function (): void {
    expect(fn() => $this->reader->read('/nonexistent/file.txt'))
        ->toThrow(FileNotFoundException::class);
});

test('reads and parses JSON file', function (): void {
    $path = $this->tempDir . '/test.json';
    file_put_contents($path, '{"name": "test", "version": "1.0"}');

    $data = $this->reader->readJson($path);

    expect($data)->toBe(['name' => 'test', 'version' => '1.0']);
});

test('throws FileOperationException for invalid JSON', function (): void {
    $path = $this->tempDir . '/invalid.json';
    file_put_contents($path, 'not valid json');

    expect(fn() => $this->reader->readJson($path))
        ->toThrow(FileOperationException::class);
});

test('reads and parses YAML file', function (): void {
    $path = $this->tempDir . '/test.yaml';
    file_put_contents($path, "name: test\nversion: '1.0'");

    $data = $this->reader->readYaml($path);

    expect($data)->toBe(['name' => 'test', 'version' => '1.0']);
});

test('throws YamlParseException for invalid YAML', function (): void {
    $path = $this->tempDir . '/invalid.yaml';
    file_put_contents($path, "invalid: yaml: : content");

    expect(fn() => $this->reader->readYaml($path))
        ->toThrow(YamlParseException::class);
});

test('returns empty array for YAML with scalar value', function (): void {
    $path = $this->tempDir . '/scalar.yaml';
    file_put_contents($path, 'just a string');

    $data = $this->reader->readYaml($path);

    expect($data)->toBe([]);
});

test('checks if file exists', function (): void {
    $existingPath = $this->tempDir . '/exists.txt';
    file_put_contents($existingPath, 'test');

    expect($this->reader->exists($existingPath))->toBeTrue();
    expect($this->reader->exists('/nonexistent/file.txt'))->toBeFalse();
});

test('reads file or returns default', function (): void {
    $existingPath = $this->tempDir . '/exists.txt';
    file_put_contents($existingPath, 'content');

    expect($this->reader->readOrDefault($existingPath))->toBe('content');
    expect($this->reader->readOrDefault('/nonexistent', 'default'))->toBe('default');
});
