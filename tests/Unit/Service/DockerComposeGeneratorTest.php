<?php

declare(strict_types=1);

// ABOUTME: Tests for DockerComposeGenerator service.
// ABOUTME: Validates docker-compose.yml generation from configuration.

namespace Seaman\Tests\Unit\Service;

use Seaman\Service\DockerComposeGenerator;
use Seaman\Service\ConfigManager;
use Seaman\Service\TemplateRenderer;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/seaman-test-' . uniqid();
    mkdir($this->tempDir, 0755, true);

    $templateDir = __DIR__ . '/../../../src/Template';
    $this->renderer = new TemplateRenderer($templateDir);
    $this->generator = new DockerComposeGenerator($this->renderer);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        array_map('unlink', glob($this->tempDir . '/*'));
        rmdir($this->tempDir);
    }
});

test('generates docker-compose.yml from configuration', function () {
    $configManager = new ConfigManager(__DIR__ . '/../../Fixtures/configs');
    copy(__DIR__ . '/../../Fixtures/configs/full-seaman.yaml', __DIR__ . '/../../Fixtures/configs/seaman.yaml');

    $config = $configManager->load();
    $yaml = $this->generator->generate($config);

    expect($yaml)->toContain('version: \'3.8\'')
        ->and($yaml)->toContain('services:')
        ->and($yaml)->toContain('app:')
        ->and($yaml)->toContain('database:')
        ->and($yaml)->toContain('redis:')
        ->and($yaml)->toContain('networks:')
        ->and($yaml)->toContain('volumes:');

    // Cleanup
    unlink(__DIR__ . '/../../Fixtures/configs/seaman.yaml');
});

test('includes only enabled services', function () {
    $configManager = new ConfigManager(__DIR__ . '/../../Fixtures/configs');
    copy(__DIR__ . '/../../Fixtures/configs/minimal-seaman.yaml', __DIR__ . '/../../Fixtures/configs/seaman.yaml');

    $config = $configManager->load();
    $yaml = $this->generator->generate($config);

    expect($yaml)->toContain('app:')
        ->and($yaml)->not->toContain('database:')
        ->and($yaml)->not->toContain('redis:');

    unlink(__DIR__ . '/../../Fixtures/configs/seaman.yaml');
});
