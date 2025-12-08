<?php

declare(strict_types=1);

// ABOUTME: Integration tests for XdebugCommand.
// ABOUTME: Validates Xdebug toggle functionality.

/**
 * @property string $tempDir
 * @property string $originalDir
 */

namespace Seaman\Tests\Integration\Command;

use Seaman\Application;
use Seaman\Exception\CommandNotAvailableException;
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


test('xdebug command is not available without seaman.yaml', function () {
    $application = new Application();

    // Command should not be available in Not Initialized mode
    expect(fn() => $application->find('xdebug'))
        ->toThrow(CommandNotAvailableException::class);
});

test('xdebug command is available with seaman.yaml', function () {
    // Set up managed mode by creating seaman.yaml
    TestHelper::copyFixture('database-seaman.yaml', $this->tempDir);
    file_put_contents($this->tempDir . '/docker-compose.yml', 'version: "3"');

    $application = new Application();
    $commandTester = new CommandTester($application->find('xdebug'));

    // Command should be available but will fail due to no running container
    $commandTester->execute(['mode' => 'on']);

    // Should not throw CommandNotAvailableException - command runs but fails
    expect($commandTester->getStatusCode())->toBeIn([0, 1]);
});

test('xdebug command validates mode argument', function () {
    // Set up managed mode
    TestHelper::copyFixture('database-seaman.yaml', $this->tempDir);
    file_put_contents($this->tempDir . '/docker-compose.yml', 'version: "3"');

    $application = new Application();
    $commandTester = new CommandTester($application->find('xdebug'));

    $commandTester->execute(['mode' => 'invalid']);

    expect($commandTester->getStatusCode())->toBe(1);
    expect($commandTester->getDisplay())->toContain('must be one of "on", "off"');
});
