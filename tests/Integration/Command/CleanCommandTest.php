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
    chdir($this->tempDir);
});

afterEach(function () {
    HeadlessMode::reset();
    chdir($this->originalDir);
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
    expect($commandTester->getDisplay())->toContain('Cancelled');

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

    $commandTester->execute([]);

    $display = $commandTester->getDisplay();
    expect($display)->toContain('docker-compose.yml');
    expect($display)->toContain('seaman.yaml');
    expect($display)->toContain('.seaman');
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
    mkdir($this->tempDir . '/.seaman', 0755, true);

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
    mkdir($this->tempDir . '/.seaman', 0755, true);

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
    mkdir($this->tempDir . '/.seaman', 0755, true);

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

    $commandTester->execute([]);

    $display = $commandTester->getDisplay();
    expect($display)->toContain('will be restored');
    expect($display)->toContain('docker-compose.yml.backup-2024-01-01-120000');
});
