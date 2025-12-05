<?php

// ABOUTME: Tests for ProjectDetector service.
// ABOUTME: Validates Symfony project type detection logic.

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Seaman\Enum\ProjectType;
use Seaman\Service\ProjectDetector;
use Seaman\Service\SymfonyDetector;

final class ProjectDetectorTest extends TestCase
{
    private string $testRoot;
    private ProjectDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testRoot = sys_get_temp_dir() . '/seaman_test_' . uniqid();
        mkdir($this->testRoot, 0777, true);

        $symfonyDetector = new SymfonyDetector();
        $this->detector = new ProjectDetector($symfonyDetector);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDirectory($this->testRoot);
    }

    public function test_detects_api_platform_project(): void
    {
        // Arrange - create minimal Symfony + API Platform project
        $this->createComposerJson([
            'require' => [
                'symfony/framework-bundle' => '^6.0',
                'api-platform/core' => '^3.0',
            ],
        ]);
        mkdir($this->testRoot . '/config', 0777, true);
        mkdir($this->testRoot . '/bin', 0777, true);
        file_put_contents($this->testRoot . '/bin/console', '#!/usr/bin/env php');
        chmod($this->testRoot . '/bin/console', 0755);

        // Act
        $type = $this->detector->detectProjectType($this->testRoot);

        // Assert
        $this->assertSame(ProjectType::ApiPlatform, $type);
    }

    public function test_detects_web_app_with_twig(): void
    {
        // Arrange - create Symfony + Twig project
        $this->createComposerJson([
            'require' => [
                'symfony/framework-bundle' => '^6.0',
                'symfony/twig-bundle' => '^6.0',
            ],
        ]);
        mkdir($this->testRoot . '/config', 0777, true);
        mkdir($this->testRoot . '/bin', 0777, true);
        file_put_contents($this->testRoot . '/bin/console', '#!/usr/bin/env php');
        chmod($this->testRoot . '/bin/console', 0755);

        // Act
        $type = $this->detector->detectProjectType($this->testRoot);

        // Assert
        $this->assertSame(ProjectType::WebApplication, $type);
    }

    public function test_detects_web_app_with_webpack_encore(): void
    {
        // Arrange - create Symfony + Webpack Encore project
        $this->createComposerJson([
            'require' => [
                'symfony/framework-bundle' => '^6.0',
                'symfony/webpack-encore-bundle' => '^2.0',
            ],
        ]);
        mkdir($this->testRoot . '/config', 0777, true);
        mkdir($this->testRoot . '/bin', 0777, true);
        file_put_contents($this->testRoot . '/bin/console', '#!/usr/bin/env php');
        chmod($this->testRoot . '/bin/console', 0755);

        // Act
        $type = $this->detector->detectProjectType($this->testRoot);

        // Assert
        $this->assertSame(ProjectType::WebApplication, $type);
    }

    public function test_detects_microservice(): void
    {
        // Arrange - create minimal Symfony without UI dependencies
        $this->createComposerJson([
            'require' => [
                'symfony/framework-bundle' => '^6.0',
                'symfony/console' => '^6.0',
            ],
        ]);
        mkdir($this->testRoot . '/config', 0777, true);
        mkdir($this->testRoot . '/bin', 0777, true);
        file_put_contents($this->testRoot . '/bin/console', '#!/usr/bin/env php');
        chmod($this->testRoot . '/bin/console', 0755);

        // Act
        $type = $this->detector->detectProjectType($this->testRoot);

        // Assert
        $this->assertSame(ProjectType::Microservice, $type);
    }

    public function test_detects_skeleton_project(): void
    {
        // Arrange - create bare minimum Symfony
        $this->createComposerJson([
            'require' => [
                'symfony/framework-bundle' => '^6.0',
            ],
        ]);
        mkdir($this->testRoot . '/config', 0777, true);
        mkdir($this->testRoot . '/src', 0777, true);
        file_put_contents($this->testRoot . '/src/Kernel.php', '<?php');

        // Act
        $type = $this->detector->detectProjectType($this->testRoot);

        // Assert
        $this->assertSame(ProjectType::Skeleton, $type);
    }

    public function test_returns_skeleton_for_non_symfony_project(): void
    {
        // Arrange - empty directory

        // Act
        $type = $this->detector->detectProjectType($this->testRoot);

        // Assert
        $this->assertSame(ProjectType::Skeleton, $type);
    }

    public function test_is_symfony_project_returns_true_for_symfony(): void
    {
        // Arrange - create minimal Symfony project
        $this->createComposerJson([
            'require' => [
                'symfony/framework-bundle' => '^6.0',
            ],
        ]);
        mkdir($this->testRoot . '/config', 0777, true);

        // Act
        $isSymfony = $this->detector->isSymfonyProject($this->testRoot);

        // Assert
        $this->assertTrue($isSymfony);
    }

    public function test_is_symfony_project_returns_false_for_non_symfony(): void
    {
        // Arrange - empty directory

        // Act
        $isSymfony = $this->detector->isSymfonyProject($this->testRoot);

        // Assert
        $this->assertFalse($isSymfony);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createComposerJson(array $data): void
    {
        file_put_contents(
            $this->testRoot . '/composer.json',
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
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
