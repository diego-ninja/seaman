<?php

// ABOUTME: Tests for Application mode filtering functionality.
// ABOUTME: Verifies commands are filtered based on operating mode.

declare(strict_types=1);

namespace Seaman\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seaman\Application;
use Seaman\Exception\CommandNotAvailableException;
use Symfony\Component\Console\Command\Command;

final class ApplicationTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/seaman-test-' . uniqid();
        mkdir($this->testDir, 0755, true);
        chdir($this->testDir);
    }

    protected function tearDown(): void
    {
        chdir('/tmp');
        $this->removeDirectory($this->testDir);
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

    #[Test]
    public function it_shows_mode_in_application_name_for_managed_mode(): void
    {
        // Setup managed mode
        mkdir($this->testDir . '/.seaman', 0755, true);
        file_put_contents($this->testDir . '/.seaman/seaman.yaml', 'project_name: test');

        $app = new Application();

        $this->assertStringContainsString('Managed', $app->getName());
    }

    #[Test]
    public function it_shows_mode_in_application_name_for_unmanaged_mode(): void
    {
        // Setup unmanaged mode (docker-compose.yaml but no seaman.yaml)
        file_put_contents($this->testDir . '/docker-compose.yaml', 'version: "3"');

        $app = new Application();

        $this->assertStringContainsString('Unmanaged', $app->getName());
    }

    #[Test]
    public function it_shows_mode_in_application_name_for_uninitialized_mode(): void
    {
        // No files - uninitialized mode
        $app = new Application();

        $this->assertStringContainsString('Not Initialized', $app->getName());
    }

    #[Test]
    public function it_filters_managed_only_commands_in_unmanaged_mode(): void
    {
        // Setup unmanaged mode
        file_put_contents($this->testDir . '/docker-compose.yaml', 'version: "3"');

        $app = new Application();
        $commands = $app->all();

        // service:add is managed-only, should not be present
        $this->assertArrayNotHasKey('service:add', $commands);
        $this->assertArrayNotHasKey('service:remove', $commands);
        $this->assertArrayNotHasKey('service:list', $commands);

        // start should be present (works in all modes)
        $this->assertArrayHasKey('seaman:start', $commands);
    }

    #[Test]
    public function it_shows_all_commands_in_managed_mode(): void
    {
        // Setup managed mode
        mkdir($this->testDir . '/.seaman', 0755, true);
        file_put_contents($this->testDir . '/.seaman/seaman.yaml', 'project_name: test');

        $app = new Application();
        $commands = $app->all();

        // All commands should be present
        $this->assertArrayHasKey('service:add', $commands);
        $this->assertArrayHasKey('seaman:start', $commands);
    }

    #[Test]
    public function it_throws_command_not_available_for_filtered_command(): void
    {
        // Setup unmanaged mode
        file_put_contents($this->testDir . '/docker-compose.yaml', 'version: "3"');

        $app = new Application();

        $this->expectException(CommandNotAvailableException::class);
        $this->expectExceptionMessage('service:add');
        $this->expectExceptionMessage('Unmanaged');

        $app->find('service:add');
    }

    #[Test]
    public function it_finds_available_commands_normally(): void
    {
        // Setup unmanaged mode
        file_put_contents($this->testDir . '/docker-compose.yaml', 'version: "3"');

        $app = new Application();

        // start works in all modes
        $command = $app->find('seaman:start');

        $this->assertInstanceOf(Command::class, $command);
        $this->assertEquals('seaman:start', $command->getName());
    }

    #[Test]
    public function it_always_shows_init_command(): void
    {
        // Even in uninitialized mode, init should be available
        $app = new Application();
        $commands = $app->all();

        $this->assertArrayHasKey('seaman:init', $commands);
    }
}
