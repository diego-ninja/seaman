<?php

declare(strict_types=1);

// ABOUTME: Integration tests for StatusCommand.
// ABOUTME: Validates container status display functionality.

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


test('status command requires docker-compose.yml', function () {
    $application = new Application();
    $commandTester = new CommandTester($application->find('status'));

    expect(fn() => $commandTester->execute([]))
        ->toThrow(\RuntimeException::class, 'Docker Compose file not found');
});

test('status command works in unmanaged mode without seaman.yaml', function () {
    TestHelper::createMinimalDockerCompose($this->tempDir);

    $application = new Application();
    $commandTester = new CommandTester($application->find('status'));

    $commandTester->execute([]);

    expect($commandTester->getStatusCode())->toBe(0);
})->group('docker');

test('status command resolves canonical, detected, and custom Compose service names', function () {
    TestHelper::createMinimalDockerCompose($this->tempDir);

    $binDir = $this->tempDir . '/bin';
    mkdir($binDir);
    file_put_contents($binDir . '/docker', <<<'SH'
#!/bin/sh
if [ "$#" -ne 6 ] || [ "$1" != "compose" ] || [ "$2" != "-f" ] || [ "$4" != "ps" ] || [ "$5" != "--format" ] || [ "$6" != "json" ]; then
    exit 64
fi

printf '%s\n' \
'{"ID":"redis-id","Name":"project-redis-1","Image":"postgres:16","Service":"redis","State":"running","Health":"healthy","RunningFor":"1 minute","Publishers":[]}' \
'{"ID":"image-id","Name":"project-image-db-1","Image":"postgres:16","Service":"primary-db","State":"running","Health":"healthy","RunningFor":"1 minute","Publishers":[]}' \
'{"ID":"name-id","Name":"project-name-db-1","Image":"acme/database:latest","Service":"postgres","State":"running","Health":"healthy","RunningFor":"1 minute","Publishers":[]}' \
'{"ID":"worker-id","Name":"project-worker-1","Image":"acme/worker:latest","Service":"worker","State":"running","Health":"healthy","RunningFor":"1 minute","Publishers":[]}'
SH);
    chmod($binDir . '/docker', 0755);

    $originalPath = getenv('PATH');
    putenv('PATH=' . $binDir);

    try {
        $application = new Application();
        $commandTester = new CommandTester($application->find('status'));
        $commandTester->execute([]);
    } finally {
        $originalPath === false ? putenv('PATH') : putenv('PATH=' . $originalPath);
    }

    expect($commandTester->getStatusCode())->toBe(0)
        ->and($commandTester->getDisplay())->toContain('🧵 project-redis-1')
        ->and($commandTester->getDisplay())->toContain('🐘 project-image-db-1')
        ->and($commandTester->getDisplay())->toContain('🐘 project-name-db-1')
        ->and($commandTester->getDisplay())->toContain('⚙️  project-worker-1');
});
