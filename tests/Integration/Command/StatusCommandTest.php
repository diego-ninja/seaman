<?php

declare(strict_types=1);

// ABOUTME: Integration tests for StatusCommand.
// ABOUTME: Validates container status display functionality.

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


test('status command requires docker-compose.yml', function () {
    $application = new Application();
    $commandTester = new CommandTester($application->find('status'));

    expect(fn() => $commandTester->execute([]))
        ->toThrow(\RuntimeException::class, 'Docker Compose file not found');
});

test('status command works in unmanaged mode without seaman.yaml', function () {
    TestHelper::createMinimalDockerCompose($this->tempDir);

    $application = new Application();
    $commandTester = new CommandTester($application->find('status'));

    $commandTester->execute([]);

    expect($commandTester->getStatusCode())->toBe(0);
});
