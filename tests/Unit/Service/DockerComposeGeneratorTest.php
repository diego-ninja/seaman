<?php

declare(strict_types=1);

// ABOUTME: Tests for DockerComposeGenerator service.
// ABOUTME: Validates docker-compose.yml generation from configuration.

namespace Tests\Unit\Service;

use Seaman\Enum\PhpVersion;
use Seaman\Enum\Service;
use Seaman\Service\DockerComposeGenerator;
use Seaman\Service\TemplateRenderer;
use Seaman\Service\TraefikLabelGenerator;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\CustomServiceCollection;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\ProxyConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\VolumeConfig;
use Seaman\ValueObject\XdebugConfig;

beforeEach(function (): void {
    $templateDir = __DIR__ . '/../../../src/Template';
    $this->renderer = new TemplateRenderer($templateDir);
    $this->labelGenerator = new TraefikLabelGenerator();
    $this->generator = new DockerComposeGenerator($this->renderer, $this->labelGenerator);
});

test('generates docker-compose.yml from configuration', function (): void {
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig(PhpVersion::Php84, $xdebug);

    $config = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: $php,
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
    );

    $yaml = $this->generator->generate($config);

    expect($yaml)->toContain('services:')
        ->and($yaml)->toContain('app:')
        ->and($yaml)->toContain('image: seaman/seaman-php8.4:latest')
        ->and($yaml)->toContain('build:')
        ->and($yaml)->toContain('dockerfile: .seaman/Dockerfile');
});

test('includes only enabled services', function (): void {
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig(PhpVersion::Php84, $xdebug);

    $redis = new ServiceConfig(
        name: 'redis',
        enabled: true,
        type: Service::Redis,
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

test('merges custom services into generated compose', function (): void {
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig(PhpVersion::Php84, $xdebug);

    $customServices = new CustomServiceCollection([
        'my-app' => [
            'image' => 'myapp:latest',
            'ports' => ['8080:80'],
        ],
    ]);

    $config = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: $php,
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
        customServices: $customServices,
    );

    $yaml = $this->generator->generate($config);

    expect($yaml)->toContain('my-app:')
        ->and($yaml)->toContain("image: 'myapp:latest'")
        ->and($yaml)->toContain('networks:')
        ->and($yaml)->toContain('seaman');
});

test('custom services get seaman network added if missing', function (): void {
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig(PhpVersion::Php84, $xdebug);

    $customServices = new CustomServiceCollection([
        'worker' => [
            'image' => 'worker:latest',
        ],
    ]);

    $config = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: $php,
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
        customServices: $customServices,
    );

    $yaml = $this->generator->generate($config);

    // The worker service should have networks: [seaman] added
    expect($yaml)->toContain('worker:')
        ->and($yaml)->toContain('seaman');
});

test('generates docker-compose without traefik labels when proxy disabled', function (): void {
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig(PhpVersion::Php84, $xdebug);

    $config = new Configuration(
        projectName: 'testproject',
        version: '1.0',
        php: $php,
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
        proxy: ProxyConfig::disabled(),
    );

    $yaml = $this->generator->generate($config);

    expect($yaml)->not->toContain('traefik.enable=true')
        ->and($yaml)->toContain('ports:');
});

test('generates docker-compose with traefik labels when proxy enabled', function (): void {
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig(PhpVersion::Php84, $xdebug);

    $config = new Configuration(
        projectName: 'testproject',
        version: '1.0',
        php: $php,
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
        proxy: ProxyConfig::default('testproject'),
    );

    $yaml = $this->generator->generate($config);

    expect($yaml)->toContain('traefik.enable=true');
});
