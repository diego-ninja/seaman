<?php

declare(strict_types=1);

// ABOUTME: Integration tests for ProxyConfigureDnsCommand.
// ABOUTME: Validates DNS configuration for Traefik local domains.

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


test('proxy:configure-dns is not available in uninitialized mode', function () {
    $application = new Application();

    expect(fn() => $application->find('proxy:configure-dns'))
        ->toThrow(CommandNotAvailableException::class);
});

test('proxy:configure-dns is not available in unmanaged mode', function () {
    // Only docker-compose.yml, no seaman.yaml = unmanaged mode
    file_put_contents($this->tempDir . '/docker-compose.yml', 'version: "3"');

    $application = new Application();

    expect(fn() => $application->find('proxy:configure-dns'))
        ->toThrow(CommandNotAvailableException::class);
});

test('proxy:configure-dns is available in managed mode', function () {
    TestHelper::copyFixture('database-seaman.yaml', $this->tempDir);
    file_put_contents($this->tempDir . '/docker-compose.yml', 'version: "3"');

    $application = new Application();
    $command = $application->find('proxy:configure-dns');

    expect($command->getName())->toBe('proxy:configure-dns');
});

test('proxy:configure-dns has dns alias', function () {
    TestHelper::copyFixture('database-seaman.yaml', $this->tempDir);
    file_put_contents($this->tempDir . '/docker-compose.yml', 'version: "3"');

    $application = new Application();
    $command = $application->find('dns');

    expect($command->getName())->toBe('proxy:configure-dns');
});

// Note: Tests that execute the command are skipped because they trigger
// actual system DNS configuration which requires sudo and system services
