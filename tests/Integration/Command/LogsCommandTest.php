<?php

declare(strict_types=1);

// ABOUTME: Integration tests for LogsCommand.
// ABOUTME: Validates container log viewing functionality.

/**
 * @property string $tempDir
 * @property string $originalDir
 * @property string|false $originalPath
 * @property string $dockerArgumentsPath
 */

namespace Seaman\Tests\Integration\Command;

use Seaman\Application;
use Seaman\Tests\Integration\TestHelper;
use Seaman\UI\HeadlessMode;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    HeadlessMode::reset();
    HeadlessMode::enable();
    $tempDir = TestHelper::createTempDir();
    $resolvedTempDir = realpath($tempDir);
    if ($resolvedTempDir === false) {
        throw new \RuntimeException('Failed to resolve temporary directory');
    }
    $this->tempDir = $resolvedTempDir;
    $originalDir = getcwd();
    if ($originalDir === false) {
        throw new \RuntimeException('Failed to get current working directory');
    }
    $this->originalDir = $originalDir;
    $this->originalPath = getenv('PATH');
    $this->dockerArgumentsPath = $this->tempDir . '/docker-arguments';
    $binDir = $this->tempDir . '/bin';
    mkdir($binDir);
    file_put_contents(
        $binDir . '/docker',
        "#!/bin/sh\nprintf '%s' \"\$*\" > " . escapeshellarg($this->dockerArgumentsPath) . "\nprintf 'test log'\n",
    );
    chmod($binDir . '/docker', 0755);
    putenv('PATH=' . $binDir . ':' . ($this->originalPath === false ? '' : $this->originalPath));
    chdir($this->tempDir);
});

afterEach(function () {
    HeadlessMode::reset();
    chdir($this->originalDir);
    TestHelper::cleanupDocker($this->tempDir);
    $this->originalPath === false ? putenv('PATH') : putenv('PATH=' . $this->originalPath);
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
    TestHelper::createMinimalDockerCompose($this->tempDir);

    $application = new Application();
    $commandTester = new CommandTester($application->find('logs'));

    $commandTester->execute(['service' => 'app']);

    expect($commandTester->getStatusCode())->toBe(0)
        ->and(file_get_contents($this->dockerArgumentsPath))->toBe(
            'compose -f ' . $this->tempDir . '/docker-compose.yml logs app',
        );
});

test('logs command with tail option', function () {
    TestHelper::createMinimalDockerCompose($this->tempDir);

    $application = new Application();
    $commandTester = new CommandTester($application->find('logs'));

    $commandTester->execute([
        'service' => 'app',
        '--tail' => '100',
    ]);

    expect($commandTester->getStatusCode())->toBe(0)
        ->and(file_get_contents($this->dockerArgumentsPath))->toBe(
            'compose -f ' . $this->tempDir . '/docker-compose.yml logs --tail 100 app',
        );
});

test('logs command with since option', function () {
    TestHelper::createMinimalDockerCompose($this->tempDir);

    $application = new Application();
    $commandTester = new CommandTester($application->find('logs'));

    $commandTester->execute([
        'service' => 'app',
        '--since' => '1h',
    ]);

    expect($commandTester->getStatusCode())->toBe(0)
        ->and(file_get_contents($this->dockerArgumentsPath))->toBe(
            'compose -f ' . $this->tempDir . '/docker-compose.yml logs --since 1h app',
        );
});
