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

test('start command requires seaman.yaml', function () {
    $application = new Application();
    $commandTester = new CommandTester($application->find('start'));

    $commandTester->execute([]);

    expect($commandTester->getStatusCode())->toBe(1);
    expect($commandTester->getDisplay())->toContain('seaman.yaml not found');
});

test('start command requires docker-compose file', function () {
    TestHelper::copyFixture('minimal-seaman.yaml', $this->tempDir);

    $application = new Application();
    $commandTester = new CommandTester($application->find('start'));

    $commandTester->execute([]);

    expect($commandTester->getStatusCode())->toBe(1);
    expect($commandTester->getDisplay())->toContain('Docker Compose file not found');
});
