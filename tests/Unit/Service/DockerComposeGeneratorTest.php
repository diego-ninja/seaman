<?php

declare(strict_types=1);

// ABOUTME: Tests for DockerComposeGenerator service.
// ABOUTME: Validates docker-compose.yml generation from configuration.

namespace Tests\Unit\Service;

use Seaman\Service\DockerComposeGenerator;
use Seaman\Service\TemplateRenderer;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\VolumeConfig;
use Seaman\ValueObject\XdebugConfig;

beforeEach(function (): void {
    $templateDir = __DIR__ . '/../../../src/Template';
    $this->renderer = new TemplateRenderer($templateDir);
    $this->generator = new DockerComposeGenerator($this->renderer);
});

test('generates docker-compose.yml from configuration', function (): void {
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig('8.4', ['intl', 'opcache'], $xdebug);

    $config = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: $php,
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
    );

    $yaml = $this->generator->generate($config);

    expect($yaml)->toContain('version: \'3.8\'')
        ->and($yaml)->toContain('services:')
        ->and($yaml)->toContain('app:')
        ->and($yaml)->toContain('image: seaman/seaman:latest')
        ->and($yaml)->toContain('build:')
        ->and($yaml)->toContain('dockerfile: .seaman/Dockerfile');
});

test('includes only enabled services', function (): void {
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig('8.4', ['intl'], $xdebug);

    $redis = new ServiceConfig(
        name: 'redis',
        enabled: true,
        type: 'redis',
        version: '7-alpine',
        port: 6379,
        additionalPorts: [],
        environmentVariables: [],
    );

    $config = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: $php,
        services: new ServiceCollection(['redis' => $redis]),
        volumes: new VolumeConfig(['redis']),
    );

    $yaml = $this->generator->generate($config);

    expect($yaml)->toContain('redis:')
        ->and($yaml)->toContain('image: redis:7-alpine');
});
