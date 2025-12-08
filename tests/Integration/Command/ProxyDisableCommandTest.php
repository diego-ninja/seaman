<?php

declare(strict_types=1);

// ABOUTME: Integration tests for ProxyDisableCommand.
// ABOUTME: Validates Traefik proxy disablement functionality.

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
    TestHelper::removeTempDir($this->tempDir);
});


test('proxy:disable is not available in uninitialized mode', function () {
    $application = new Application();

    expect(fn() => $application->find('proxy:disable'))
        ->toThrow(CommandNotAvailableException::class);
});

test('proxy:disable is not available in unmanaged mode', function () {
    // Only docker-compose.yml, no seaman.yaml = unmanaged mode
    file_put_contents($this->tempDir . '/docker-compose.yml', 'version: "3"');

    $application = new Application();

    expect(fn() => $application->find('proxy:disable'))
        ->toThrow(CommandNotAvailableException::class);
});

test('proxy:disable is available in managed mode', function () {
    TestHelper::copyFixture('database-seaman.yaml', $this->tempDir);
    file_put_contents($this->tempDir . '/docker-compose.yml', 'version: "3"');

    $application = new Application();
    $command = $application->find('proxy:disable');

    expect($command->getName())->toBe('proxy:disable');
});

test('proxy:disable shows already disabled message when proxy is disabled', function () {
    TestHelper::copyFixture('proxy-disabled-seaman.yaml', $this->tempDir);
    file_put_contents($this->tempDir . '/docker-compose.yml', 'version: "3"');

    $application = new Application();
    $commandTester = new CommandTester($application->find('proxy:disable'));
    $commandTester->execute([]);

    $output = $commandTester->getDisplay();

    expect($commandTester->getStatusCode())->toBe(0);
    expect($output)->toContain('already disabled');
});

test('proxy:disable disables proxy successfully', function () {
    TestHelper::copyFixture('proxy-enabled-seaman.yaml', $this->tempDir);
    file_put_contents($this->tempDir . '/docker-compose.yml', 'version: "3"');

    $application = new Application();
    $commandTester = new CommandTester($application->find('proxy:disable'));
    $commandTester->execute([]);

    $output = $commandTester->getDisplay();

    expect($commandTester->getStatusCode())->toBe(0);
    expect($output)->toContain('Proxy disabled successfully');
    expect($output)->toContain('seaman restart');
});
