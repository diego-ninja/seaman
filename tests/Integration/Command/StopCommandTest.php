<?php

declare(strict_types=1);

// ABOUTME: Integration tests for StopCommand.
// ABOUTME: Validates container stopping functionality.

/**
 * @property string $tempDir
 * @property string $originalDir
 */

namespace Seaman\Tests\Integration\Command;

use Seaman\Application;
use Seaman\Tests\Integration\TestHelper;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    $this->tempDir = TestHelper::createTempDir();
    $originalDir = getcwd();
    if ($originalDir === false) {
        throw new \RuntimeException('Failed to get current working directory');
    }
    $this->originalDir = $originalDir;
    chdir($this->tempDir);
});

afterEach(function () {
    chdir($this->originalDir);
    TestHelper::removeTempDir($this->tempDir);
});


test('stop command requires docker-compose.yml', function () {
    $application = new Application();
    $commandTester = new CommandTester($application->find('stop'));

    expect(fn() => $commandTester->execute([]))
        ->toThrow(\RuntimeException::class, 'Docker Compose file not found');
});

test('stop command works in unmanaged mode without seaman.yaml', function () {
    TestHelper::createMinimalDockerCompose($this->tempDir);

    $application = new Application();
    $commandTester = new CommandTester($application->find('stop'));

    $commandTester->execute([]);

    expect($commandTester->getStatusCode())->toBe(0);
});
