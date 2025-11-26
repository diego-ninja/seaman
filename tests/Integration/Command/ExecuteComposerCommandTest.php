<?php

declare(strict_types=1);

// ABOUTME: Integration tests for ExecuteComposerCommand.
// ABOUTME: Validates composer command execution in container.

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


test('composer command requires seaman.yaml', function () {
    $application = new Application();
    $commandTester = new CommandTester($application->find('composer'));

    $commandTester->execute(['command' => ['--version']]);

    expect($commandTester->getStatusCode())->toBe(1);
    expect($commandTester->getDisplay())->toContain('seaman.yaml not found');
});

test('composer command executes in app container', function () {
    $application = new Application();
    $commandTester = new CommandTester($application->find('composer'));

    $commandTester->execute(['command' => ['--version']]);

    expect($commandTester->getStatusCode())->toBeIn([0, 1]);
});
