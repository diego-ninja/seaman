<?php

declare(strict_types=1);

// ABOUTME: Integration tests for DestroyCommand.
// ABOUTME: Validates complete environment teardown functionality.

/**
 * @property string $tempDir
 * @property string $originalDir
 */

namespace Seaman\Tests\Integration\Command;

use Seaman\Application;
use Seaman\Tests\Integration\TestHelper;
use Seaman\UI\HeadlessMode;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    HeadlessMode::reset();
    HeadlessMode::enable();
    $this->tempDir = TestHelper::createTempDir();
    $originalDir = getcwd();
    if ($originalDir === false) {
        throw new \RuntimeException('Failed to get current working directory');
    }
    $this->originalDir = $originalDir;
    chdir($this->tempDir);
});

afterEach(function () {
    HeadlessMode::reset();
    chdir($this->originalDir);
    TestHelper::cleanupDocker($this->tempDir);
    TestHelper::removeTempDir($this->tempDir);
});


test('destroy command requires docker-compose.yml', function () {
    // Preset confirmation to true so we get past the prompt
    HeadlessMode::preset([
        'This will remove all containers, networks, and volumes. Are you sure?' => true,
    ]);

    $application = new Application();
    $commandTester = new CommandTester($application->find('destroy'));

    // Command throws RuntimeException when docker-compose.yml is missing
    expect(fn() => $commandTester->execute([]))
        ->toThrow(\RuntimeException::class, 'Docker Compose file not found');
});

test('destroy command cancels when user declines', function () {
    // Default confirm() returns false in headless mode
    $application = new Application();
    $commandTester = new CommandTester($application->find('destroy'));

    $commandTester->execute([]);

    expect($commandTester->getStatusCode())->toBe(0);
    expect($commandTester->getDisplay())->toContain('Operation cancelled');
});
