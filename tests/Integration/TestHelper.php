<?php

declare(strict_types=1);

// ABOUTME: Helper functions for integration tests.
// ABOUTME: Provides utilities for creating temporary test environments.

namespace Seaman\Tests\Integration;

class TestHelper
{
    /**
     * Creates a temporary directory for testing.
     *
     * @return string The path to the temporary directory
     */
    public static function createTempDir(): string
    {
        $tempDir = sys_get_temp_dir() . '/seaman-test-' . uniqid();
        mkdir($tempDir, 0755, true);
        mkdir($tempDir . '/.seaman', 0755, true);

        return $tempDir;
    }

    /**
     * Removes a temporary directory recursively.
     *
     * @param string $dir The directory to remove
     */
    public static function removeTempDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        exec("rm -rf " . escapeshellarg($dir));
    }

    /**
     * Copies a fixture configuration file to a temporary directory.
     *
     * @param string $fixtureName The name of the fixture file (without path)
     * @param string $targetDir The target directory
     */
    public static function copyFixture(string $fixtureName, string $targetDir): void
    {
        $fixturesDir = __DIR__ . '/../Fixtures/configs';
        $sourcePath = $fixturesDir . '/' . $fixtureName;
        $targetPath = $targetDir . '/seaman.yaml';

        if (!file_exists($sourcePath)) {
            throw new \RuntimeException("Fixture not found: {$sourcePath}");
        }

        copy($sourcePath, $targetPath);
    }

    /**
     * Creates a minimal docker-compose.yml file for testing.
     *
     * @param string $targetDir The target directory
     */
    public static function createMinimalDockerCompose(string $targetDir): void
    {
        $composeContent = <<<'YAML'
version: '3.8'

services:
  app:
    image: php:8.4-cli
    networks:
      - seaman

networks:
  seaman:
    driver: bridge

volumes: {}
YAML;

        file_put_contents($targetDir . '/docker-compose.yml', $composeContent);
    }
}
