<?php

declare(strict_types=1);

// ABOUTME: Unit tests for ConfigureCommand.
// ABOUTME: Validates interactive service configuration command.

namespace Seaman\Tests\Unit\Command;

use Seaman\Application;
use Seaman\Tests\Integration\TestHelper;
use Seaman\UI\HeadlessMode;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    HeadlessMode::reset();
    HeadlessMode::enable();
    /** @phpstan-ignore property.notFound */
    $this->tempDir = TestHelper::createTempDir();
    $originalDir = getcwd();
    if ($originalDir === false) {
        throw new \RuntimeException('Failed to get current working directory');
    }
    /** @phpstan-ignore property.notFound */
    $this->originalDir = $originalDir;
    /** @phpstan-ignore argument.type */
    chdir($this->tempDir);
});

afterEach(function () {
    HeadlessMode::reset();
    /** @phpstan-ignore argument.type */
    chdir($this->originalDir);
    /** @phpstan-ignore argument.type */
    TestHelper::removeTempDir($this->tempDir);
});

test('configure command requires managed mode', function () {
    // Without seaman.yaml, mode is Uninitialized
    // and configure command should not be available

    $application = new Application();

    expect(fn() => $application->find('configure'))
        ->toThrow(\Seaman\Exception\CommandNotAvailableException::class);
});

test('configure command requires valid service name', function () {
    /** @phpstan-ignore argument.type */
    TestHelper::copyFixture('minimal-seaman.yaml', $this->tempDir);

    $application = new Application();
    $commandTester = new CommandTester($application->find('configure'));

    $commandTester->execute(['service' => 'nonexistent']);

    expect($commandTester->getStatusCode())->toBe(1);
    expect($commandTester->getDisplay())->toContain('not found');
});

test('configure command requires service to be enabled', function () {
    /** @phpstan-ignore argument.type */
    TestHelper::copyFixture('minimal-seaman.yaml', $this->tempDir);

    $application = new Application();
    $commandTester = new CommandTester($application->find('configure'));

    $commandTester->execute(['service' => 'postgresql']);

    expect($commandTester->getStatusCode())->toBe(1);
    expect($commandTester->getDisplay())->toContain('not enabled');
});

test('configure command updates service configuration', function () {
    /** @phpstan-ignore argument.type */
    TestHelper::copyFixture('database-seaman.yaml', $this->tempDir);

    HeadlessMode::preset([
        'Database name' => 'new_database',
        'Database user' => 'new_user',
        'Database password' => 'new_password',
        'PostgreSQL version' => '16',
        'Port' => '5432',
        'What would you like to do?' => 'none',
    ]);

    $application = new Application();
    $commandTester = new CommandTester($application->find('configure'));

    $commandTester->execute(['service' => 'postgresql']);

    expect($commandTester->getStatusCode())->toBe(0);
    expect($commandTester->getDisplay())->toContain('Configuration saved');

    /** @phpstan-ignore binaryOp.invalid */
    $yamlPath = $this->tempDir . '/.seaman/seaman.yaml';
    expect(file_exists($yamlPath))->toBeTrue();

    $content = file_get_contents($yamlPath);
    expect($content)->toContain('new_database');
    expect($content)->toContain('new_user');
    expect($content)->toContain('new_password');
});
