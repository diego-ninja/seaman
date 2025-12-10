<?php

declare(strict_types=1);

// ABOUTME: Integration tests for ExecuteCommand (console).
// ABOUTME: Validates Symfony console command execution in container.

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
    TestHelper::removeTempDir($this->tempDir);
});


test('console command requires docker-compose.yml', function () {
    $application = new Application();
    $commandTester = new CommandTester($application->find('console'));

    // Command throws RuntimeException when docker-compose.yml is missing
    expect(fn() => $commandTester->execute(['args' => ['list']]))
        ->toThrow(\RuntimeException::class, 'Docker Compose file not found');
});

test('console command works in unmanaged mode without seaman.yaml', function () {
    // Create docker-compose.yml without seaman.yaml
    file_put_contents($this->tempDir . '/docker-compose.yml', "services:\n  app:\n    image: php:8.4");

    $application = new Application();
    $commandTester = new CommandTester($application->find('console'));

    // Command will try to execute but container won't exist - that's expected
    $commandTester->execute(['args' => ['list']]);

    // Should not fail because of missing seaman.yaml
    expect($commandTester->getDisplay())->not->toContain('seaman.yaml not found');
});
