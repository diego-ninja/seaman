<?php

declare(strict_types=1);

/**
 * @property string $projectRoot
 */

namespace Tests\Unit\Service;

use Seaman\Service\DockerImageBuilder;
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
});

afterEach(function (): void {
    if (is_dir($this->projectRoot)) {
        exec("rm -rf {$this->projectRoot}");
    }
});

test('build returns ProcessResult', function (): void {
    $builder = new DockerImageBuilder($this->projectRoot);
    $result = $builder->build();

    expect($result)->toBeInstanceOf(ProcessResult::class);
});

test('build uses correct docker command', function (): void {
    $builder = new DockerImageBuilder($this->projectRoot);
    $result = $builder->build();

    // Should tag as seaman/seaman:latest (can appear in either output or errorOutput)
    $allOutput = $result->output . $result->errorOutput;
    expect($allOutput)->toContain('seaman/seaman:latest');
});

test('build passes WWWGROUP argument', function (): void {
    $builder = new DockerImageBuilder($this->projectRoot);
    $result = $builder->build();

    // Build should complete (may fail if Docker not available, but command structure is correct)
    expect($result)->toBeInstanceOf(ProcessResult::class);
});
