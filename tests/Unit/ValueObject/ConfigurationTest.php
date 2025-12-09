<?php

declare(strict_types=1);

// ABOUTME: Tests for Configuration root value object.
// ABOUTME: Validates complete configuration structure.

namespace Seaman\Tests\Unit\ValueObject;

use Seaman\Enum\PhpVersion;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\CustomServiceCollection;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\ProxyConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\VolumeConfig;
use Seaman\ValueObject\XdebugConfig;

test('creates complete configuration', function (): void {
    $xdebug = new XdebugConfig(true, 'PHPSTORM', 'localhost');
    $php = new PhpConfig(PhpVersion::Php84, $xdebug);
    $services = new ServiceCollection([]);
    $volumes = new VolumeConfig([]);

    $config = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: $php,
        services: $services,
        volumes: $volumes,
    );

    expect($config->projectName)->toBe('test-project')
        ->and($config->version)->toBe('1.0')
        ->and($config->php)->toBe($php)
        ->and($config->services)->toBe($services)
        ->and($config->volumes)->toBe($volumes);
});

test('configuration is immutable', function (): void {
    $xdebug = new XdebugConfig(true, 'PHPSTORM', 'localhost');
    $php = new PhpConfig(PhpVersion::Php84, $xdebug);
    $services = new ServiceCollection([]);
    $volumes = new VolumeConfig([]);

    $config = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: $php,
        services: $services,
        volumes: $volumes,
    );

    $reflection = new \ReflectionClass($config);
    expect($reflection->isReadOnly())->toBeTrue();
});

test('configuration with explicit proxy config', function (): void {
    $xdebug = new XdebugConfig(true, 'PHPSTORM', 'localhost');
    $php = new PhpConfig(PhpVersion::Php84, $xdebug);
    $services = new ServiceCollection([]);
    $volumes = new VolumeConfig([]);
    $proxy = new ProxyConfig(
        enabled: true,
        domainPrefix: 'custom-project',
        certResolver: 'mkcert',
        dashboard: true,
    );

    $config = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: $php,
        services: $services,
        volumes: $volumes,
        proxy: $proxy,
    );

    expect($config->proxy)->toBe($proxy)
        ->and($config->proxy()->domainPrefix)->toBe('custom-project')
        ->and($config->proxy()->certResolver)->toBe('mkcert');
});

test('configuration generates default proxy config when not provided', function (): void {
    $xdebug = new XdebugConfig(true, 'PHPSTORM', 'localhost');
    $php = new PhpConfig(PhpVersion::Php84, $xdebug);
    $services = new ServiceCollection([]);
    $volumes = new VolumeConfig([]);

    $config = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: $php,
        services: $services,
        volumes: $volumes,
    );

    $proxy = $config->proxy();

    expect($proxy)->toBeInstanceOf(ProxyConfig::class)
        ->and($proxy->enabled)->toBeTrue()
        ->and($proxy->domainPrefix)->toBe('test-project')
        ->and($proxy->certResolver)->toBe('selfsigned')
        ->and($proxy->dashboard)->toBeTrue();
});

test('configuration includes custom services', function (): void {
    $xdebug = new XdebugConfig(true, 'PHPSTORM', 'localhost');
    $php = new PhpConfig(PhpVersion::Php84, $xdebug);
    $services = new ServiceCollection([]);
    $volumes = new VolumeConfig([]);
    $customServices = new CustomServiceCollection([
        'my-app' => ['image' => 'myapp:latest'],
    ]);

    $config = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: $php,
        services: $services,
        volumes: $volumes,
        customServices: $customServices,
    );

    expect($config->customServices)->toBe($customServices)
        ->and($config->hasCustomServices())->toBeTrue();
});

test('hasCustomServices returns false when empty', function (): void {
    $xdebug = new XdebugConfig(true, 'PHPSTORM', 'localhost');
    $php = new PhpConfig(PhpVersion::Php84, $xdebug);
    $services = new ServiceCollection([]);
    $volumes = new VolumeConfig([]);

    $config = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: $php,
        services: $services,
        volumes: $volumes,
    );

    expect($config->hasCustomServices())->toBeFalse();
});

test('customServices defaults to empty collection', function (): void {
    $xdebug = new XdebugConfig(true, 'PHPSTORM', 'localhost');
    $php = new PhpConfig(PhpVersion::Php84, $xdebug);
    $services = new ServiceCollection([]);
    $volumes = new VolumeConfig([]);

    $config = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: $php,
        services: $services,
        volumes: $volumes,
    );

    expect($config->customServices)->toBeInstanceOf(CustomServiceCollection::class)
        ->and($config->customServices->isEmpty())->toBeTrue();
});

test('with() creates a copy with updated proxy', function (): void {
    $xdebug = new XdebugConfig(true, 'PHPSTORM', 'localhost');
    $php = new PhpConfig(PhpVersion::Php84, $xdebug);
    $services = new ServiceCollection([]);
    $volumes = new VolumeConfig([]);

    $config = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: $php,
        services: $services,
        volumes: $volumes,
    );

    $newProxy = ProxyConfig::disabled();
    $newConfig = $config->with(proxy: $newProxy);

    expect($newConfig)->not->toBe($config)
        ->and($newConfig->proxy)->toBe($newProxy)
        ->and($newConfig->projectName)->toBe('test-project')
        ->and($newConfig->version)->toBe('1.0')
        ->and($newConfig->php)->toBe($php)
        ->and($newConfig->services)->toBe($services)
        ->and($newConfig->volumes)->toBe($volumes);
});

test('with() creates a copy with updated services', function (): void {
    $xdebug = new XdebugConfig(true, 'PHPSTORM', 'localhost');
    $php = new PhpConfig(PhpVersion::Php84, $xdebug);
    $services = new ServiceCollection([]);
    $volumes = new VolumeConfig([]);

    $config = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: $php,
        services: $services,
        volumes: $volumes,
    );

    $newServices = new ServiceCollection([]);
    $newConfig = $config->with(services: $newServices);

    expect($newConfig)->not->toBe($config)
        ->and($newConfig->services)->toBe($newServices)
        ->and($newConfig->projectName)->toBe('test-project');
});

test('with() can update multiple fields at once', function (): void {
    $xdebug = new XdebugConfig(true, 'PHPSTORM', 'localhost');
    $php = new PhpConfig(PhpVersion::Php84, $xdebug);
    $services = new ServiceCollection([]);
    $volumes = new VolumeConfig([]);

    $config = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: $php,
        services: $services,
        volumes: $volumes,
    );

    $newProxy = ProxyConfig::disabled();
    $newCustomServices = new CustomServiceCollection(['test' => ['image' => 'test:latest']]);
    $newConfig = $config->with(
        proxy: $newProxy,
        customServices: $newCustomServices,
    );

    expect($newConfig->proxy)->toBe($newProxy)
        ->and($newConfig->customServices)->toBe($newCustomServices)
        ->and($newConfig->projectName)->toBe('test-project');
});

test('with() preserves original configuration', function (): void {
    $xdebug = new XdebugConfig(true, 'PHPSTORM', 'localhost');
    $php = new PhpConfig(PhpVersion::Php84, $xdebug);
    $services = new ServiceCollection([]);
    $volumes = new VolumeConfig([]);
    $originalProxy = ProxyConfig::default('test-project');

    $config = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: $php,
        services: $services,
        volumes: $volumes,
        proxy: $originalProxy,
    );

    $config->with(proxy: ProxyConfig::disabled());

    expect($config->proxy)->toBe($originalProxy);
});
