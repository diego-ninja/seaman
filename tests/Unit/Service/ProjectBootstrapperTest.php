<?php

// ABOUTME: Tests for Symfony project bootstrapper service.
// ABOUTME: Verifies project creation for different project types.

declare(strict_types=1);

namespace Tests\Unit\Service;

use Seaman\Service\ProjectBootstrapper;
use Seaman\ValueObject\ProjectType;
use Symfony\Component\Process\Process;

test('bootstrap creates web application project', function (): void {
    $bootstrapper = new ProjectBootstrapper();

    $tempDir = sys_get_temp_dir() . '/test-bootstrap-' . uniqid();
    mkdir($tempDir);

    // Mock the process execution - we'll test command generation, not actual execution
    $command = $bootstrapper->getBootstrapCommand(
        ProjectType::WebApplication,
        'test-app',
        $tempDir,
    );

    expect($command)->toContain('symfony');
    expect($command)->toContain('new');
    expect($command)->toContain('test-app');
    expect($command)->toContain('--webapp');

    rmdir($tempDir);
});

test('bootstrap creates api platform project', function (): void {
    $bootstrapper = new ProjectBootstrapper();

    $tempDir = sys_get_temp_dir() . '/test-bootstrap-api-' . uniqid();
    mkdir($tempDir);

    $commands = $bootstrapper->getBootstrapCommands(
        ProjectType::ApiPlatform,
        'test-api',
        $tempDir,
    );

    expect($commands)->toHaveCount(2);
    expect($commands[0])->toContain('symfony new');
    expect($commands[1])->toContain('composer require api');

    rmdir($tempDir);
});

test('bootstrap creates microservice project', function (): void {
    $bootstrapper = new ProjectBootstrapper();

    $tempDir = sys_get_temp_dir() . '/test-bootstrap-micro-' . uniqid();
    mkdir($tempDir);

    $command = $bootstrapper->getBootstrapCommand(
        ProjectType::Microservice,
        'test-micro',
        $tempDir,
    );

    expect($command)->toContain('symfony');
    expect($command)->toContain('new');
    expect($command)->toContain('--webapp=false');

    rmdir($tempDir);
});

test('bootstrap creates skeleton project', function (): void {
    $bootstrapper = new ProjectBootstrapper();

    $tempDir = sys_get_temp_dir() . '/test-bootstrap-skeleton-' . uniqid();
    mkdir($tempDir);

    $command = $bootstrapper->getBootstrapCommand(
        ProjectType::Skeleton,
        'test-skeleton',
        $tempDir,
    );

    expect($command)->toContain('symfony');
    expect($command)->toContain('new');
    expect($command)->toContain('--webapp=false');

    rmdir($tempDir);
});
