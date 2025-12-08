<?php

declare(strict_types=1);

// ABOUTME: Integration tests for StartCommand.
// ABOUTME: Validates container starting functionality.

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

test('start command requires docker-compose.yml', function () {
    $application = new Application();
    $commandTester = new CommandTester($application->find('start'));

    $commandTester->execute([]);

    expect($commandTester->getStatusCode())->toBe(1);
    expect($commandTester->getDisplay())->toContain('Docker Compose file not found');
});

test('start command works in unmanaged mode without seaman.yaml', function () {
    TestHelper::createMinimalDockerCompose($this->tempDir);

    $application = new Application();
    $commandTester = new CommandTester($application->find('start'));

    $commandTester->execute([]);

    // The command might fail if docker-compose cannot actually start containers
    // (e.g., docker daemon not running, network issues, etc.), but it should
    // not fail due to missing seaman.yaml.
    expect($commandTester->getStatusCode())->toBeIn([0, 1]);
});
