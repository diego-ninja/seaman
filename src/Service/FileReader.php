<?php

// ABOUTME: Centralized service for reading files with proper error handling.
// ABOUTME: Eliminates boilerplate file reading code across the codebase.

declare(strict_types=1);

namespace Seaman\Service;

use Seaman\Exception\FileNotFoundException;
use Seaman\Exception\FileOperationException;
use Seaman\Exception\YamlParseException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final readonly class FileReader
{
    /**
     * Read file contents as a string.
     *
     * @throws FileNotFoundException When file does not exist
     * @throws FileOperationException When file cannot be read
     */
    public function read(string $path): string
    {
        if (!file_exists($path)) {
            throw FileNotFoundException::create($path);
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw FileOperationException::readFailed($path);
        }

        return $content;
    }

    /**
     * Read and parse a JSON file.
     *
     * @return array<string, mixed>
     * @throws FileNotFoundException When file does not exist
     * @throws FileOperationException When file cannot be read or JSON is invalid
     */
    public function readJson(string $path): array
    {
        $content = $this->read($path);

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw FileOperationException::readFailed($path, $e);
        }

        if (!is_array($data)) {
            throw FileOperationException::readFailed($path);
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * Read and parse a YAML file.
     *
     * @return array<string, mixed>
     * @throws FileNotFoundException When file does not exist
     * @throws FileOperationException When file cannot be read
     * @throws YamlParseException When YAML parsing fails
     */
    public function readYaml(string $path): array
    {
        $content = $this->read($path);

        try {
            $data = Yaml::parse($content);
        } catch (ParseException $e) {
            throw YamlParseException::create($path, $e->getMessage(), $e);
        }

        if (!is_array($data)) {
            return [];
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * Check if file exists.
     */
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * Read file contents if it exists, or return default.
     */
    public function readOrDefault(string $path, string $default = ''): string
    {
        if (!file_exists($path)) {
            return $default;
        }

        $content = file_get_contents($path);

        return $content !== false ? $content : $default;
    }
}
