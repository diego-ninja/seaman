<?php

declare(strict_types=1);

// ABOUTME: Integration tests for InspectCommand.
// ABOUTME: Validates project status display functionality.

namespace Seaman\Tests\Integration\Command;

use Seaman\Application;
use Seaman\Exception\CommandNotAvailableException;
use Seaman\Tests\Integration\TestHelper;
use Seaman\UI\HeadlessMode;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @property string $tempDir
 * @property string $originalDir
 */
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


test('inspect command is not available in uninitialized mode', function () {
    // No seaman.yaml = uninitialized mode
    $application = new Application();

    expect(fn() => $application->find('inspect'))
        ->toThrow(CommandNotAvailableException::class);
});

test('inspect command is available with seaman.yaml (managed mode)', function () {
    TestHelper::copyFixture('database-seaman.yaml', $this->tempDir);
    file_put_contents($this->tempDir . '/docker-compose.yml', "services:\n  app:\n    image: php:8.4");

    $application = new Application();
    $command = $application->find('inspect');

    expect($command->getName())->toBe('seaman:inspect');
});

test('inspect command executes successfully', function () {
    TestHelper::copyFixture('database-seaman.yaml', $this->tempDir);
    file_put_contents($this->tempDir . '/docker-compose.yml', "services:\n  app:\n    image: php:8.4");

    $application = new Application();
    $commandTester = new CommandTester($application->find('inspect'));
    $commandTester->execute([]);

    // Table output goes to Laravel Prompts' output stream, not CommandTester
    // So we just verify the command completes successfully
    expect($commandTester->getStatusCode())->toBe(0);
});

test('inspect command has describe alias', function () {
    TestHelper::copyFixture('database-seaman.yaml', $this->tempDir);
    file_put_contents($this->tempDir . '/docker-compose.yml', "services:\n  app:\n    image: php:8.4");

    $application = new Application();
    $command = $application->find('describe');

    expect($command->getName())->toBe('seaman:inspect');
});

test('inspect command works in unmanaged mode with docker-compose only', function () {
    // Only docker-compose.yaml, no seaman.yaml = unmanaged mode
    // ModeDetector looks for docker-compose.yaml (not .yml)
    file_put_contents($this->tempDir . '/docker-compose.yaml', "services:\n  app:\n    image: php:8.4");

    $application = new Application();

    // In unmanaged mode, inspect should still be available
    // but will fail trying to load config
    expect(fn() => $application->find('inspect'))
        ->not->toThrow(CommandNotAvailableException::class);
});
