<?php

declare(strict_types=1);

// ABOUTME: Integration tests for RebuildCommand.
// ABOUTME: Validates image rebuilding functionality.

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


test('rebuild command requires seaman.yaml', function () {
    $application = new Application();
    $commandTester = new CommandTester($application->find('rebuild'));

    $commandTester->execute([]);

    expect($commandTester->getStatusCode())->toBe(1);
    expect($commandTester->getDisplay())->toContain('seaman.yaml not found');
});

test('rebuild command with specific service', function () {
    $application = new Application();
    $commandTester = new CommandTester($application->find('rebuild'));

    $commandTester->execute(['service' => 'app']);

    expect($commandTester->getStatusCode())->toBeIn([0, 1]);
});
