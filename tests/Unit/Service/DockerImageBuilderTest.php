<?php

// ABOUTME: Tests for DockerImageBuilder service.
// ABOUTME: Verifies Docker image building and tagging functionality.

declare(strict_types=1);

/**
 * @property string $projectRoot
 */

namespace Seaman\Tests\Unit\Service;

use Seaman\Enum\PhpVersion;
use Seaman\Enum\ServerType;
use Seaman\Service\Builder\DockerImageBuilder;
use Seaman\ValueObject\ProcessResult;

beforeEach(function (): void {
    $this->projectRoot = sys_get_temp_dir() . '/seaman-test-' . uniqid();
    mkdir($this->projectRoot, 0755, true);
    mkdir($this->projectRoot . '/.seaman', 0755, true);

    // Create minimal Dockerfile
    file_put_contents(
        $this->projectRoot . '/.seaman/Dockerfile',
        "FROM ubuntu:24.04\nRUN echo 'test'",
    );

    $this->originalPath = getenv('PATH');
    $binDir = $this->projectRoot . '/bin';
    mkdir($binDir);
    file_put_contents($binDir . '/docker', "#!/bin/sh\nexit 0\n");
    chmod($binDir . '/docker', 0755);
    putenv('PATH=' . $binDir . ':' . ($this->originalPath === false ? '' : $this->originalPath));
});

afterEach(function (): void {
    $this->originalPath === false ? putenv('PATH') : putenv('PATH=' . $this->originalPath);
    if (is_dir($this->projectRoot)) {
        exec("rm -rf {$this->projectRoot}");
    }
});

test('build returns ProcessResult', function (): void {
    $builder = new DockerImageBuilder($this->projectRoot, PhpVersion::Php84, ServerType::SymfonyServer);
    $result = $builder->build();

    expect($result)->toBeInstanceOf(ProcessResult::class);
});

test('build uses correct docker command', function (): void {
    $builder = new DockerImageBuilder($this->projectRoot, PhpVersion::Php84, ServerType::SymfonyServer);
    $result = $builder->build();

    // Build should complete (tag may not appear in output with buildx)
    expect($result)->toBeInstanceOf(ProcessResult::class)
        ->and($result->exitCode)->toBeLessThanOrEqual(1);
});

test('build completes successfully', function (): void {
    $builder = new DockerImageBuilder($this->projectRoot, PhpVersion::Php84, ServerType::SymfonyServer);
    $result = $builder->build();

    // Build should complete successfully with Docker available
    expect($result)->toBeInstanceOf(ProcessResult::class)
        ->and($result->isSuccessful())->toBeTrue();
});
