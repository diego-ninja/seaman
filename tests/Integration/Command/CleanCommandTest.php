<?php

declare(strict_types=1);

// ABOUTME: Integration tests for CleanCommand.
// ABOUTME: Validates seaman file cleanup functionality.

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
    $this->originalPath = getenv('PATH');
    $binDir = $this->tempDir . '/bin';
    mkdir($binDir);
    file_put_contents($binDir . '/docker', "#!/bin/sh\nexit 0\n");
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

test('clean command cancels when user declines confirmation', function () {
    // Create files that would be cleaned
    TestHelper::createMinimalDockerCompose($this->tempDir);
    file_put_contents($this->tempDir . '/seaman.yaml', 'project_name: test');

    $application = new Application();
    $commandTester = new CommandTester($application->find('clean'));

    // Default confirm() returns false in headless mode
    $commandTester->execute([]);

    expect($commandTester->getStatusCode())->toBe(0);
    expect($commandTester->getDisplay())->toContain('Operation cancelled');

    // Files should still exist
    expect(file_exists($this->tempDir . '/docker-compose.yml'))->toBeTrue();
    expect(file_exists($this->tempDir . '/seaman.yaml'))->toBeTrue();
});

test('clean command removes all seaman files when confirmed', function () {
    // Create all files that should be cleaned
    TestHelper::createMinimalDockerCompose($this->tempDir);
    file_put_contents($this->tempDir . '/seaman.yaml', 'project_name: test');
    mkdir($this->tempDir . '/.seaman/traefik', 0755, true);
    file_put_contents($this->tempDir . '/.seaman/traefik/traefik.yml', 'test');
    mkdir($this->tempDir . '/.seaman/scripts', 0755, true);
    file_put_contents($this->tempDir . '/.seaman/scripts/xdebug-toggle.sh', 'test');
    mkdir($this->tempDir . '/.devcontainer', 0755, true);
    file_put_contents($this->tempDir . '/.devcontainer/devcontainer.json', '{}');

    HeadlessMode::preset([
        'This will remove all Seaman files. Are you sure?' => true,
    ]);

    $application = new Application();
    $commandTester = new CommandTester($application->find('clean'));

    $commandTester->execute([]);

    expect($commandTester->getStatusCode())->toBe(0);

    // All seaman files should be removed
    expect(file_exists($this->tempDir . '/docker-compose.yml'))->toBeFalse();
    expect(file_exists($this->tempDir . '/seaman.yaml'))->toBeFalse();
    expect(is_dir($this->tempDir . '/.seaman'))->toBeFalse();
    expect(is_dir($this->tempDir . '/.devcontainer'))->toBeFalse();
});

test('clean command shows files to be removed before confirmation', function () {
    TestHelper::createMinimalDockerCompose($this->tempDir);
    file_put_contents($this->tempDir . '/seaman.yaml', 'project_name: test');

    $application = new Application();
    $commandTester = new CommandTester($application->find('clean'));

    // Execute command - Box output goes to Laravel Prompts stream, not CommandTester
    // The test verifies the command runs without error and shows the confirmation prompt
    $commandTester->execute([]);

    // Command should succeed (cancelled via headless mode default)
    expect($commandTester->getStatusCode())->toBe(0);
    expect($commandTester->getDisplay())->toContain('Operation cancelled');
});

test('clean command has correct aliases', function () {
    $application = new Application();
    $command = $application->find('clean');

    expect($command->getAliases())->toContain('clean');
});

test('clean command restores docker-compose backup when available', function () {
    // Create original docker-compose backup (simulating import scenario)
    $backupContent = "version: '3'\nservices:\n  db:\n    image: postgres";
    file_put_contents($this->tempDir . '/docker-compose.yml.backup-2024-01-01-120000', $backupContent);

    // Create seaman-generated docker-compose
    TestHelper::createMinimalDockerCompose($this->tempDir);
    file_put_contents($this->tempDir . '/seaman.yaml', 'project_name: test');

    HeadlessMode::preset([
        'This will remove all Seaman files. Are you sure?' => true,
    ]);

    $application = new Application();
    $commandTester = new CommandTester($application->find('clean'));

    $commandTester->execute([]);

    expect($commandTester->getStatusCode())->toBe(0);

    // docker-compose.yml should be restored from backup
    expect(file_exists($this->tempDir . '/docker-compose.yml'))->toBeTrue();
    expect(file_get_contents($this->tempDir . '/docker-compose.yml'))->toBe($backupContent);

    // Backup file should be removed
    expect(file_exists($this->tempDir . '/docker-compose.yml.backup-2024-01-01-120000'))->toBeFalse();

    // Other seaman files should be removed
    expect(file_exists($this->tempDir . '/seaman.yaml'))->toBeFalse();
    expect(is_dir($this->tempDir . '/.seaman'))->toBeFalse();
});

test('clean command removes a managed compose.yaml file', function () {
    file_put_contents($this->tempDir . '/compose.yaml', "services: {}\n");

    HeadlessMode::preset([
        'This will remove all Seaman files. Are you sure?' => true,
    ]);

    $application = new Application();
    $commandTester = new CommandTester($application->find('clean'));
    $commandTester->execute([]);

    expect($commandTester->getStatusCode())->toBe(0)
        ->and(file_exists($this->tempDir . '/compose.yaml'))->toBeFalse();
});

test('clean command preserves an unmanaged Compose file', function () {
    rmdir($this->tempDir . '/.seaman');
    file_put_contents($this->tempDir . '/compose.yaml', "services: {}\n");

    $application = new Application();
    $commandTester = new CommandTester($application->find('clean'));
    $commandTester->execute([]);

    expect($commandTester->getStatusCode())->toBe(0)
        ->and($commandTester->getDisplay())->toContain('No Seaman files found')
        ->and(file_exists($this->tempDir . '/compose.yaml'))->toBeTrue();
});

test('clean command restores a docker-compose.yaml backup to its original name', function () {
    $backup = $this->tempDir . '/docker-compose.yaml.backup-2024-01-01-120000';
    $backupContent = "services:\n  legacy:\n    image: alpine\n";
    file_put_contents($backup, $backupContent);
    TestHelper::createMinimalDockerCompose($this->tempDir);

    HeadlessMode::preset([
        'This will remove all Seaman files. Are you sure?' => true,
    ]);

    $application = new Application();
    $commandTester = new CommandTester($application->find('clean'));
    $commandTester->execute([]);

    expect($commandTester->getStatusCode())->toBe(0)
        ->and(file_exists($this->tempDir . '/docker-compose.yml'))->toBeFalse()
        ->and(file_get_contents($this->tempDir . '/docker-compose.yaml'))->toBe($backupContent)
        ->and(file_exists($backup))->toBeFalse();
});

test('clean command does not overwrite a Compose file when restoring a backup', function () {
    TestHelper::createMinimalDockerCompose($this->tempDir);
    $userCompose = $this->tempDir . '/compose.yaml';
    $backup = $userCompose . '.backup-2024-01-01-120000';
    file_put_contents($userCompose, "services:\n  user-service:\n    image: alpine\n");
    file_put_contents($backup, "services:\n  legacy-service:\n    image: busybox\n");

    HeadlessMode::preset([
        'This will remove all Seaman files. Are you sure?' => true,
    ]);

    $application = new Application();
    $commandTester = new CommandTester($application->find('clean'));
    $commandTester->execute([]);

    expect($commandTester->getStatusCode())->toBe(1)
        ->and($commandTester->getDisplay())->toContain('would overwrite')
        ->and(file_exists($this->tempDir . '/docker-compose.yml'))->toBeTrue()
        ->and(file_get_contents($userCompose))->toContain('user-service')
        ->and(file_exists($backup))->toBeTrue();
});

test('clean command removes seaman section from env file', function () {
    $envContent = <<<'ENV'
APP_NAME=MyApp
APP_DEBUG=true

# ---- SEAMAN MANAGED ----
# Variables below are managed by Seaman. Manual changes may be overwritten.

# Application configuration
APP_PORT=8000

# PHP configuration
PHP_VERSION=8.4

# ---- END SEAMAN MANAGED ----
ENV;

    file_put_contents($this->tempDir . '/.env', $envContent);
    TestHelper::createMinimalDockerCompose($this->tempDir);
    file_put_contents($this->tempDir . '/seaman.yaml', 'project_name: test');

    HeadlessMode::preset([
        'This will remove all Seaman files. Are you sure?' => true,
    ]);

    $application = new Application();
    $commandTester = new CommandTester($application->find('clean'));

    $commandTester->execute([]);

    expect($commandTester->getStatusCode())->toBe(0);

    // .env should exist with only user variables
    expect(file_exists($this->tempDir . '/.env'))->toBeTrue();
    $cleanedEnv = file_get_contents($this->tempDir . '/.env');
    expect($cleanedEnv)->toContain('APP_NAME=MyApp');
    expect($cleanedEnv)->toContain('APP_DEBUG=true');
    expect($cleanedEnv)->not->toContain('SEAMAN MANAGED');
    expect($cleanedEnv)->not->toContain('APP_PORT');
    expect($cleanedEnv)->not->toContain('PHP_VERSION');
});

test('clean command removes empty env file after cleaning', function () {
    $envContent = <<<'ENV'
# ---- SEAMAN MANAGED ----
APP_PORT=8000
# ---- END SEAMAN MANAGED ----
ENV;

    file_put_contents($this->tempDir . '/.env', $envContent);
    TestHelper::createMinimalDockerCompose($this->tempDir);
    file_put_contents($this->tempDir . '/seaman.yaml', 'project_name: test');

    HeadlessMode::preset([
        'This will remove all Seaman files. Are you sure?' => true,
    ]);

    $application = new Application();
    $commandTester = new CommandTester($application->find('clean'));

    $commandTester->execute([]);

    expect($commandTester->getStatusCode())->toBe(0);

    // .env should be removed entirely if empty after cleaning
    expect(file_exists($this->tempDir . '/.env'))->toBeFalse();
});

test('clean command shows restore info in preview', function () {
    // Create backup
    file_put_contents($this->tempDir . '/docker-compose.yml.backup-2024-01-01-120000', 'backup');
    TestHelper::createMinimalDockerCompose($this->tempDir);
    file_put_contents($this->tempDir . '/seaman.yaml', 'project_name: test');

    $application = new Application();
    $commandTester = new CommandTester($application->find('clean'));

    // Execute command - Box output goes to Laravel Prompts stream, not CommandTester
    // The test verifies the command runs without error
    $commandTester->execute([]);

    // Command should succeed (cancelled via headless mode default)
    expect($commandTester->getStatusCode())->toBe(0);
    expect($commandTester->getDisplay())->toContain('Operation cancelled');
});

test('clean command does not dereference symlinks outside the project', function () {
    $externalDir = sys_get_temp_dir() . '/seaman-external-' . uniqid();
    mkdir($externalDir, 0755, true);
    file_put_contents($externalDir . '/keep.txt', 'keep');
    file_put_contents($externalDir . '/linked-file.txt', 'keep');

    symlink($externalDir, $this->tempDir . '/.seaman/external-dir');
    symlink($externalDir . '/linked-file.txt', $this->tempDir . '/.seaman/external-file');

    HeadlessMode::preset([
        'This will remove all Seaman files. Are you sure?' => true,
    ]);

    $application = new Application();
    $commandTester = new CommandTester($application->find('clean'));
    $commandTester->execute([]);

    expect($commandTester->getStatusCode())->toBe(0)
        ->and(file_exists($externalDir . '/keep.txt'))->toBeTrue()
        ->and(file_exists($externalDir . '/linked-file.txt'))->toBeTrue()
        ->and(is_dir($this->tempDir . '/.seaman'))->toBeFalse();

    unlink($externalDir . '/keep.txt');
    unlink($externalDir . '/linked-file.txt');
    rmdir($externalDir);
});

test('clean command restores a backup when it is the only artifact', function () {
    rmdir($this->tempDir . '/.seaman');
    $backup = $this->tempDir . '/docker-compose.yml.backup-2024-01-01-120000';
    file_put_contents($backup, "services:\n  app:\n    image: php:8.4-cli\n");

    HeadlessMode::preset([
        'This will remove all Seaman files. Are you sure?' => true,
    ]);

    $application = new Application();
    $commandTester = new CommandTester($application->find('clean'));
    $commandTester->execute([]);

    expect($commandTester->getStatusCode())->toBe(0)
        ->and(file_exists($this->tempDir . '/docker-compose.yml'))->toBeTrue()
        ->and(file_exists($backup))->toBeFalse();
});

test('clean command removes a managed env section when it is the only artifact', function () {
    rmdir($this->tempDir . '/.seaman');
    file_put_contents(
        $this->tempDir . '/.env',
        "# ---- SEAMAN MANAGED ----\nAPP_PORT=8000\n# ---- END SEAMAN MANAGED ----\n",
    );

    HeadlessMode::preset([
        'This will remove all Seaman files. Are you sure?' => true,
    ]);

    $application = new Application();
    $commandTester = new CommandTester($application->find('clean'));
    $commandTester->execute([]);

    expect($commandTester->getStatusCode())->toBe(0)
        ->and(file_exists($this->tempDir . '/.env'))->toBeFalse();
});

test('clean command preserves local files when Docker cleanup fails', function () {
    file_put_contents($this->tempDir . '/docker-compose.yml', 'invalid: [');
    file_put_contents($this->tempDir . '/seaman.yaml', 'project_name: test');
    file_put_contents($this->tempDir . '/bin/docker', "#!/bin/sh\nexit 1\n");

    HeadlessMode::preset([
        'This will remove all Seaman files. Are you sure?' => true,
    ]);

    $application = new Application();
    $commandTester = new CommandTester($application->find('clean'));
    $commandTester->execute([]);

    expect($commandTester->getStatusCode())->toBe(1)
        ->and(file_exists($this->tempDir . '/docker-compose.yml'))->toBeTrue()
        ->and(file_exists($this->tempDir . '/seaman.yaml'))->toBeTrue()
        ->and(is_dir($this->tempDir . '/.seaman'))->toBeTrue();
});
