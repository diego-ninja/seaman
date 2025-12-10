<?php

declare(strict_types=1);

// ABOUTME: Integration tests for ProxyEnableCommand.
// ABOUTME: Validates Traefik proxy enablement functionality.

namespace Seaman\Tests\Integration\Command;

use Seaman\Application;
use Seaman\Exception\CommandNotAvailableException;
use Seaman\Tests\Integration\TestHelper;
use Seaman\UI\HeadlessMode;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @property string $tempDir
 * @property string $originalDir
 */
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


test('proxy:enable is not available in uninitialized mode', function () {
    $application = new Application();

    expect(fn() => $application->find('proxy:enable'))
        ->toThrow(CommandNotAvailableException::class);
});

test('proxy:enable is not available in unmanaged mode', function () {
    // Only docker-compose.yml, no seaman.yaml = unmanaged mode
    file_put_contents($this->tempDir . '/docker-compose.yml', "services:\n  app:\n    image: php:8.4");

    $application = new Application();

    expect(fn() => $application->find('proxy:enable'))
        ->toThrow(CommandNotAvailableException::class);
});

test('proxy:enable is available in managed mode', function () {
    TestHelper::copyFixture('database-seaman.yaml', $this->tempDir);
    file_put_contents($this->tempDir . '/docker-compose.yml', "services:\n  app:\n    image: php:8.4");

    $application = new Application();
    $command = $application->find('proxy:enable');

    expect($command->getName())->toBe('proxy:enable');
});

test('proxy:enable shows already enabled message when proxy is enabled', function () {
    TestHelper::copyFixture('proxy-enabled-seaman.yaml', $this->tempDir);
    file_put_contents($this->tempDir . '/docker-compose.yml', "services:\n  app:\n    image: php:8.4");

    $application = new Application();
    $commandTester = new CommandTester($application->find('proxy:enable'));
    $commandTester->execute([]);

    $output = $commandTester->getDisplay();

    expect($commandTester->getStatusCode())->toBe(0);
    expect($output)->toContain('already enabled');
});

test('proxy:enable enables proxy successfully', function () {
    TestHelper::copyFixture('proxy-disabled-seaman.yaml', $this->tempDir);
    file_put_contents($this->tempDir . '/docker-compose.yml', "services:\n  app:\n    image: php:8.4");

    $application = new Application();
    $commandTester = new CommandTester($application->find('proxy:enable'));
    $commandTester->execute([]);

    $output = $commandTester->getDisplay();

    expect($commandTester->getStatusCode())->toBe(0);
    expect($output)->toContain('Proxy enabled successfully');
    expect($output)->toContain('seaman restart');
});
