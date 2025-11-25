<?php

declare(strict_types=1);

// ABOUTME: Base test case for all Seaman tests.
// ABOUTME: Provides common test utilities and setup.

namespace Seaman\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected string $fixturesPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturesPath = __DIR__ . '/Fixtures';
    }

    /**
     * Get fixture file path.
     */
    protected function fixture(string $path): string
    {
        return $this->fixturesPath . '/' . $path;
    }

    /**
     * Load fixture file contents.
     */
    protected function loadFixture(string $path): string
    {
        $fullPath = $this->fixture($path);
        if (!file_exists($fullPath)) {
            throw new \RuntimeException("Fixture not found: {$path}");
        }

        $content = file_get_contents($fullPath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read fixture: {$path}");
        }

        return $content;
    }

    /**
     * Create temporary directory for tests.
     */
    protected function createTempDir(): string
    {
        $tmpDir = sys_get_temp_dir() . '/seaman-test-' . uniqid();
        if (!mkdir($tmpDir, 0755, true)) {
            throw new \RuntimeException("Cannot create temp dir: {$tmpDir}");
        }

        return $tmpDir;
    }

    /**
     * Remove directory recursively.
     */
    protected function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
