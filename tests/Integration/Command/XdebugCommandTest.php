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
    TestHelper::cleanupDocker($this->tempDir);
    TestHelper::removeTempDir($this->tempDir);
});


test('xdebug command is not available without seaman.yaml', function () {
    $application = new Application();

    // Command should not be available in Not Initialized mode
    expect(fn() => $application->find('xdebug'))
        ->toThrow(CommandNotAvailableException::class);
});

test('xdebug command is available with seaman.yaml', function () {
    TestHelper::copyFixture('database-seaman.yaml', $this->tempDir);

    $application = new Application();
    $command = $application->find('xdebug');

    expect($command->getName())->toBe('seaman:xdebug');
});

test('xdebug command validates mode argument', function () {
    TestHelper::copyFixture('database-seaman.yaml', $this->tempDir);

    $application = new Application();
    $commandTester = new CommandTester($application->find('xdebug'));

    $commandTester->execute(['mode' => 'invalid']);

    expect($commandTester->getStatusCode())->toBe(1);
    expect($commandTester->getDisplay())->toContain('must be one of "on", "off"');
});
