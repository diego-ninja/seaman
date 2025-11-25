<?php

declare(strict_types=1);

// ABOUTME: Tests for DockerManager service.
// ABOUTME: Validates docker-compose command execution and error handling.

namespace Seaman\Tests\Unit\Service;

use Seaman\Service\DockerManager;
use Seaman\ValueObject\LogOptions;
use Seaman\ValueObject\ProcessResult;

/**
 * @property string $tempDir
 * @property DockerManager $manager
 */
beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/seaman-test-' . uniqid();
    mkdir($this->tempDir . '/.seaman', 0755, true);

    // Create a minimal docker-compose.yml for testing
    $composeContent = <<<YAML
services:
  web:
    image: nginx:latest
  db:
    image: mysql:8.0
YAML;

    file_put_contents($this->tempDir . '/.seaman/docker-compose.yml', $composeContent);
    $this->manager = new DockerManager($this->tempDir);
});

afterEach(function () {
    /** @var string $tempDir */
    $tempDir = $this->tempDir;
    if (is_dir($tempDir)) {
        // Remove all files recursively
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($tempDir);
    }
});

test('start returns ProcessResult for all services', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->start();

    expect($result)->toBeInstanceOf(ProcessResult::class);
    expect($result->exitCode)->toBeInt();
    expect($result->output)->toBeString();
    expect($result->errorOutput)->toBeString();
});

test('start returns ProcessResult for specific service', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->start('web');

    expect($result)->toBeInstanceOf(ProcessResult::class);
    expect($result->exitCode)->toBeInt();
});

test('stop returns ProcessResult for all services', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->stop();

    expect($result)->toBeInstanceOf(ProcessResult::class);
    expect($result->exitCode)->toBeInt();
});

test('stop returns ProcessResult for specific service', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->stop('web');

    expect($result)->toBeInstanceOf(ProcessResult::class);
    expect($result->exitCode)->toBeInt();
});

test('restart returns ProcessResult for all services', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->restart();

    expect($result)->toBeInstanceOf(ProcessResult::class);
    expect($result->exitCode)->toBeInt();
});

test('restart returns ProcessResult for specific service', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->restart('db');

    expect($result)->toBeInstanceOf(ProcessResult::class);
    expect($result->exitCode)->toBeInt();
});

test('execute runs command in service container', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->execute('web', ['ls', '-la']);

    expect($result)->toBeInstanceOf(ProcessResult::class);
    expect($result->exitCode)->toBeInt();
});

test('execute handles single command argument', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->execute('web', ['pwd']);

    expect($result)->toBeInstanceOf(ProcessResult::class);
});

test('logs returns ProcessResult with default options', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->logs('web', new LogOptions());

    expect($result)->toBeInstanceOf(ProcessResult::class);
    expect($result->exitCode)->toBeInt();
});

test('logs returns ProcessResult with follow option', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->logs('web', new LogOptions(follow: true));

    expect($result)->toBeInstanceOf(ProcessResult::class);
});

test('logs returns ProcessResult with tail option', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->logs('web', new LogOptions(tail: 100));

    expect($result)->toBeInstanceOf(ProcessResult::class);
});

test('logs returns ProcessResult with since option', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->logs('web', new LogOptions(since: '1h'));

    expect($result)->toBeInstanceOf(ProcessResult::class);
});

test('logs returns ProcessResult with all options combined', function () {
    /** @var DockerManager $manager */
    $manager = $this->manager;

    $result = $manager->logs('web', new LogOptions(
        follow: true,
        tail: 50,
        since: '30m',
    ));

    expect($result)->toBeInstanceOf(ProcessResult::class);
});

test('throws exception when docker-compose.yml does not exist', function () {
    $invalidDir = sys_get_temp_dir() . '/seaman-invalid-' . uniqid();
    mkdir($invalidDir . '/.seaman', 0755, true);

    $manager = new DockerManager($invalidDir);

    try {
        $manager->start();
    } finally {
        // Cleanup
        rmdir($invalidDir . '/.seaman');
        rmdir($invalidDir);
    }
})->throws(\RuntimeException::class);

test('handles process failure gracefully', function () {
    /** @var string $tempDir */
    $tempDir = $this->tempDir;

    // Create an invalid docker-compose.yml that will fail
    $invalidContent = <<<YAML
invalid yaml content [[[
  this will cause docker-compose to fail
YAML;

    file_put_contents($tempDir . '/.seaman/docker-compose.yml', $invalidContent);

    $manager = new DockerManager($tempDir);
    $result = $manager->start();

    expect($result)->toBeInstanceOf(ProcessResult::class);
    expect($result->isSuccessful())->toBeFalse();
});
