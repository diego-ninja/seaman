<?php

declare(strict_types=1);

// ABOUTME: Integration tests for RebuildCommand.
// ABOUTME: Validates image rebuilding functionality.

namespace Tests\Integration\Command;

use Seaman\Application;
use Seaman\Tests\Integration\TestHelper;
use Seaman\UI\HeadlessMode;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @property string $tempDir
 * @property string $originalDir
 */
beforeEach(function (): void {
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

afterEach(function (): void {
    HeadlessMode::reset();
    chdir($this->originalDir);
    TestHelper::removeTempDir($this->tempDir);
});

test('rebuild command requires seaman.yaml', function (): void {
    // Create docker-compose.yml but no seaman.yaml
    file_put_contents($this->tempDir . '/docker-compose.yml', 'version: "3"');

    $application = new Application();
    $commandTester = new CommandTester($application->find('rebuild'));

    // Command throws RuntimeException when seaman.yaml is missing
    expect(fn() => $commandTester->execute([]))
        ->toThrow(\RuntimeException::class, 'seaman.yaml not found');
});

test('rebuild command requires Dockerfile', function (): void {
    // Setup with seaman.yaml but no Dockerfile
    TestHelper::copyFixture('database-seaman.yaml', $this->tempDir);
    file_put_contents($this->tempDir . '/docker-compose.yml', 'version: "3"');

    $application = new Application();
    $commandTester = new CommandTester($application->find('rebuild'));
    $commandTester->execute([]);

    // Should fail because Dockerfile doesn't exist
    expect($commandTester->getStatusCode())->toBe(1);
});
