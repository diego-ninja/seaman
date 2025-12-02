<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Service;

use Seaman\Enum\PhpVersion;
use Seaman\Enum\ProjectType;
use Seaman\Enum\Service;
use Seaman\Service\ConfigManager;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\Service\DevContainerGenerator;
use Seaman\Service\TemplateRenderer;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\VolumeConfig;
use Seaman\ValueObject\XdebugConfig;

function createGenerator(): DevContainerGenerator
{
    $tempDir = sys_get_temp_dir() . '/seaman-test-' . uniqid();
    $templateDir = __DIR__ . '/../../../src/Template';
    $renderer = new TemplateRenderer($templateDir);
    $registry = new ServiceRegistry();
    $configManager = new ConfigManager($tempDir, $registry);

    return new DevContainerGenerator($renderer, $configManager);
}

test('builds base extensions correctly', function () {
    $generator = createGenerator();

    $config = new Configuration(
        version: '1.0',
        php: new PhpConfig(PhpVersion::Php84, new XdebugConfig(false, 'VSCODE', 'host.docker.internal')),
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
    );

    $extensions = $generator->buildExtensions($config);

    expect($extensions)->toContain('bmewburn.vscode-intelephense-client')
        ->and($extensions)->toContain('xdebug.php-debug')
        ->and($extensions)->toContain('junstyle.php-cs-fixer')
        ->and($extensions)->toContain('swordev.phpstan');
});

test('adds database extension when postgresql enabled', function () {
    $generator = createGenerator();

    $serviceConfig = new ServiceConfig(
        name: 'postgresql',
        enabled: true,
        type: Service::PostgreSQL,
        version: '16',
        port: 5432,
        additionalPorts: [],
        environmentVariables: [],
    );

    $config = new Configuration(
        version: '1.0',
        php: new PhpConfig(PhpVersion::Php84, new XdebugConfig(false, 'VSCODE', 'host.docker.internal')),
        services: new ServiceCollection(['postgresql' => $serviceConfig]),
        volumes: new VolumeConfig([]),
    );

    $extensions = $generator->buildExtensions($config);

    expect($extensions)->toContain('cweijan.vscode-database-client2');
});

test('adds redis extension when redis enabled', function () {
    $generator = createGenerator();

    $serviceConfig = new ServiceConfig(
        name: 'redis',
        enabled: true,
        type: Service::Redis,
        version: '7-alpine',
        port: 6379,
        additionalPorts: [],
        environmentVariables: [],
    );

    $config = new Configuration(
        version: '1.0',
        php: new PhpConfig(PhpVersion::Php84, new XdebugConfig(false, 'VSCODE', 'host.docker.internal')),
        services: new ServiceCollection(['redis' => $serviceConfig]),
        volumes: new VolumeConfig([]),
    );

    $extensions = $generator->buildExtensions($config);

    expect($extensions)->toContain('cisco.redis-xplorer');
});

test('adds API Platform extension when project type is ApiPlatform', function () {
    $generator = createGenerator();

    $config = new Configuration(
        version: '1.0',
        php: new PhpConfig(PhpVersion::Php84, new XdebugConfig(false, 'VSCODE', 'host.docker.internal')),
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
        projectType: ProjectType::ApiPlatform,
    );

    $extensions = $generator->buildExtensions($config);

    expect($extensions)->toContain('42crunch.vscode-openapi');
});
