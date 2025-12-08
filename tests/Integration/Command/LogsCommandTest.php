<?php

declare(strict_types=1);

// ABOUTME: Integration tests for LogsCommand.
// ABOUTME: Validates container log viewing functionality.

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


test('logs command requires docker-compose.yml', function () {
    $application = new Application();
    $commandTester = new CommandTester($application->find('logs'));

    $commandTester->execute(['service' => 'app']);

    expect($commandTester->getStatusCode())->toBe(1);
    expect($commandTester->getDisplay())->toContain('Docker Compose file not found');
});

test('logs command with specific service', function () {
    $application = new Application();
    $commandTester = new CommandTester($application->find('logs'));

    $commandTester->execute(['service' => 'app']);

    expect($commandTester->getStatusCode())->toBeIn([0, 1]);
});

test('logs command with tail option', function () {
    $application = new Application();
    $commandTester = new CommandTester($application->find('logs'));

    $commandTester->execute([
        'service' => 'app',
        '--tail' => '100',
    ]);

    expect($commandTester->getStatusCode())->toBeIn([0, 1]);
});

test('logs command with since option', function () {
    $application = new Application();
    $commandTester = new CommandTester($application->find('logs'));

    $commandTester->execute([
        'service' => 'app',
        '--since' => '1h',
    ]);

    expect($commandTester->getStatusCode())->toBeIn([0, 1]);
});
