<?php

declare(strict_types=1);

// ABOUTME: Tests for ComposeRegenerator service.
// ABOUTME: Verifies docker-compose.yml regeneration and service restart logic.

namespace Tests\Unit\Service;

use Seaman\Enum\PhpVersion;
use Seaman\Service\ComposeRegenerator;
use Seaman\Service\DockerManager;
use Seaman\Service\Generator\DockerComposeGenerator;
use Seaman\Service\Generator\TraefikLabelGenerator;
use Seaman\Service\TemplateRenderer;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\VolumeConfig;
use Seaman\ValueObject\XdebugConfig;

beforeEach(function () {
    $this->projectRoot = sys_get_temp_dir() . '/seaman-test-' . uniqid();
    mkdir($this->projectRoot);

    $this->config = new Configuration(
        projectName: 'test-project',
        version: '1.0.0',
        php: new PhpConfig(
            PhpVersion::Php84,
            XdebugConfig::default(),
        ),
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
    );
});

afterEach(function () {
    if (is_dir($this->projectRoot)) {
        array_map('unlink', glob($this->projectRoot . '/*'));
        rmdir($this->projectRoot);
    }
});

test('it creates ComposeRegenerator with required dependencies', function () {
    // Arrange
    $templateDir = __DIR__ . '/../../../src/Template';
    $renderer = new TemplateRenderer($templateDir);
    $labelGenerator = new TraefikLabelGenerator();
    $composeGenerator = new DockerComposeGenerator($renderer, $labelGenerator);
    $dockerManager = new DockerManager($this->projectRoot);

    // Act
    $regenerator = new ComposeRegenerator($composeGenerator, $dockerManager);

    // Assert
    expect($regenerator)->toBeInstanceOf(ComposeRegenerator::class);
});

test('it has regenerate method', function () {
    // Arrange
    $templateDir = __DIR__ . '/../../../src/Template';
    $renderer = new TemplateRenderer($templateDir);
    $labelGenerator = new TraefikLabelGenerator();
    $composeGenerator = new DockerComposeGenerator($renderer, $labelGenerator);
    $dockerManager = new DockerManager($this->projectRoot);

    $regenerator = new ComposeRegenerator($composeGenerator, $dockerManager);

    // Assert
    expect(method_exists($regenerator, 'regenerate'))->toBeTrue();
});

test('it has restartIfConfirmed method', function () {
    // Arrange
    $templateDir = __DIR__ . '/../../../src/Template';
    $renderer = new TemplateRenderer($templateDir);
    $labelGenerator = new TraefikLabelGenerator();
    $composeGenerator = new DockerComposeGenerator($renderer, $labelGenerator);
    $dockerManager = new DockerManager($this->projectRoot);

    $regenerator = new ComposeRegenerator($composeGenerator, $dockerManager);

    // Assert
    expect(method_exists($regenerator, 'restartIfConfirmed'))->toBeTrue();
});
