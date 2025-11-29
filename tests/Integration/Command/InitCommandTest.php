<?php

// ABOUTME: Integration tests for InitCommand.
// ABOUTME: Tests command instantiation and service integration.

declare(strict_types=1);

namespace Tests\Integration\Command;

use Seaman\Application;
use Seaman\Enum\ProjectType;
use Seaman\Service\ProjectBootstrapper;
use Seaman\Service\SymfonyDetector;

test('init command is registered in application', function (): void {
    $app = new Application();
    $command = $app->find('init');

    expect($command)->toBeInstanceOf(\Seaman\Command\InitCommand::class);
    expect($command->getName())->toBe('seaman:init');
});

test('init command has correct aliases', function (): void {
    $app = new Application();
    $command = $app->find('init');

    expect($command->getAliases())->toContain('init');
});

test('symfony detector works correctly', function (): void {
    $detector = new SymfonyDetector();

    $tempDir = sys_get_temp_dir() . '/test-detector-' . uniqid();
    mkdir($tempDir);

    $result = $detector->detect($tempDir);
    expect($result->isSymfonyProject)->toBeFalse();

    rmdir($tempDir);
});

test('project bootstrapper generates correct commands', function (): void {
    $bootstrapper = new ProjectBootstrapper();

    $command = $bootstrapper->getBootstrapCommand(
        ProjectType::WebApplication,
        'test-app',
        '/tmp',
    );

    expect($command)->toContain('symfony');
    expect($command)->toContain('new');
    expect($command)->toContain('test-app');
});
