<?php

// ABOUTME: Tests for ModeDetector service.
// ABOUTME: Validates detection of operating modes based on file existence.

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Seaman\Enum\OperatingMode;
use Seaman\Service\Detector\ModeDetector;

final class ModeDetectorTest extends TestCase
{
    private string $testRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testRoot = sys_get_temp_dir() . '/seaman_test_' . uniqid();
        mkdir($this->testRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDirectory($this->testRoot);
    }

    public function test_detects_managed_mode_when_seaman_yaml_exists(): void
    {
        // Arrange
        mkdir($this->testRoot . '/.seaman', 0777, true);
        file_put_contents($this->testRoot . '/.seaman/seaman.yaml', 'test: config');

        // Act
        $detector = new ModeDetector($this->testRoot);
        $mode = $detector->detect();

        // Assert
        $this->assertSame(OperatingMode::Managed, $mode);
        $this->assertTrue($detector->isManaged());
        $this->assertFalse($detector->requiresInitialization());
    }

    public function test_detects_unmanaged_mode_when_only_docker_compose_exists(): void
    {
        // Arrange
        file_put_contents($this->testRoot . '/docker-compose.yaml', 'version: "3.8"');

        // Act
        $detector = new ModeDetector($this->testRoot);
        $mode = $detector->detect();

        // Assert
        $this->assertSame(OperatingMode::Unmanaged, $mode);
        $this->assertFalse($detector->isManaged());
        $this->assertFalse($detector->requiresInitialization());
    }

    public function test_detects_uninitialized_mode_when_no_files_exist(): void
    {
        // Arrange - empty directory

        // Act
        $detector = new ModeDetector($this->testRoot);
        $mode = $detector->detect();

        // Assert
        $this->assertSame(OperatingMode::Uninitialized, $mode);
        $this->assertFalse($detector->isManaged());
        $this->assertTrue($detector->requiresInitialization());
    }

    public function test_seaman_yaml_takes_precedence_over_docker_compose(): void
    {
        // Arrange - both files exist
        mkdir($this->testRoot . '/.seaman', 0777, true);
        file_put_contents($this->testRoot . '/.seaman/seaman.yaml', 'test: config');
        file_put_contents($this->testRoot . '/docker-compose.yaml', 'version: "3.8"');

        // Act
        $detector = new ModeDetector($this->testRoot);
        $mode = $detector->detect();

        // Assert
        $this->assertSame(OperatingMode::Managed, $mode);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
