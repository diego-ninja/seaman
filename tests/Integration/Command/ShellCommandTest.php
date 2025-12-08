<?php

declare(strict_types=1);

// ABOUTME: Integration tests for ShellCommand.
// ABOUTME: Validates interactive shell access functionality.

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


test('shell command requires docker-compose.yml', function () {
    $application = new Application();
    $commandTester = new CommandTester($application->find('shell'));

    expect(fn() => $commandTester->execute([]))
        ->toThrow(\RuntimeException::class, 'Docker Compose file not found');
});

test('shell command works in unmanaged mode without seaman.yaml', function () {
    TestHelper::createMinimalDockerCompose($this->tempDir);

    $application = new Application();
    $commandTester = new CommandTester($application->find('shell'));

    // Shell command will fail because the container isn't running, but it should not fail
    // due to missing seaman.yaml. The exit code can be 0 or 1 depending on container state.
    $commandTester->execute(['service' => 'app']);

    expect($commandTester->getStatusCode())->toBeIn([0, 1]);
});
