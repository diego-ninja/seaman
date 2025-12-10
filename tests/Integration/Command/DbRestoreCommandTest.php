<?php

declare(strict_types=1);

// ABOUTME: Integration tests for DbRestoreCommand.
// ABOUTME: Validates database restore functionality.

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

test('db:restore command requires seaman.yaml', function () {
    $application = new Application();
    $commandTester = new CommandTester($application->find('db:restore'));

    $commandTester->execute(['file' => 'dump.sql']);

    expect($commandTester->getStatusCode())->toBe(1);
    expect($commandTester->getDisplay())->toContain('Failed to load configuration');
});

test('db:restore command requires database service', function () {
    TestHelper::copyFixture('minimal-seaman.yaml', $this->tempDir);

    $application = new Application();
    $commandTester = new CommandTester($application->find('db:restore'));

    $commandTester->execute(['file' => 'dump.sql']);

    expect($commandTester->getStatusCode())->toBe(1);
    expect($commandTester->getDisplay())->toContain('No database service found');
});

test('db:restore command requires existing file', function () {
    TestHelper::copyFixture('database-seaman.yaml', $this->tempDir);

    $application = new Application();
    $commandTester = new CommandTester($application->find('db:restore'));

    $commandTester->execute(['file' => 'nonexistent.sql']);

    expect($commandTester->getStatusCode())->toBe(1);
    expect($commandTester->getDisplay())->toContain('Dump file not found');
});

test('db:restore command requires confirmation', function () {
    TestHelper::copyFixture('database-seaman.yaml', $this->tempDir);

    $tempFile = $this->tempDir . '/test_dump.sql';
    file_put_contents($tempFile, '-- SQL dump');

    $application = new Application();
    $commandTester = new CommandTester($application->find('db:restore'));

    $commandTester->setInputs(['no']);
    $commandTester->execute(['file' => $tempFile]);

    expect($commandTester->getDisplay())->toContain('Operation cancelled');
});
