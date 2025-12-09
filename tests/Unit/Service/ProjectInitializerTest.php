<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Service;

use Seaman\Enum\PhpVersion;
use Seaman\Enum\ProjectType;
use Seaman\Enum\Service;
use Seaman\Service\ConfigManager;
use Seaman\Service\ConfigurationValidator;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\Service\ProjectInitializer;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\ProxyConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\VolumeConfig;
use Seaman\ValueObject\XdebugConfig;

beforeEach(function () {
    $this->testDir = sys_get_temp_dir() . '/seaman-init-test-' . uniqid();
    mkdir($this->testDir);
    $this->registry = ServiceRegistry::create();
});

afterEach(function () {
    if (isset($this->testDir) && is_dir($this->testDir)) {
        exec("rm -rf {$this->testDir}");
    }
});

test('initializes project with Docker files', function () {
    $config = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: new PhpConfig(
            PhpVersion::Php84,
            new XdebugConfig(true, 'seaman', 'host.docker.internal'),
        ),
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
        projectType: ProjectType::WebApplication,
    );

    $initializer = new ProjectInitializer($this->registry);
    $initializer->initializeDockerEnvironment($config, $this->testDir);

    // Verify .seaman directory was created
    expect(is_dir($this->testDir . '/.seaman'))->toBeTrue();

    // Verify docker-compose.yml was created
    expect(file_exists($this->testDir . '/docker-compose.yml'))->toBeTrue();

    // Verify seaman.yaml was created
    expect(file_exists($this->testDir . '/.seaman/seaman.yaml'))->toBeTrue();

    // Verify Dockerfile was created
    expect(file_exists($this->testDir . '/.seaman/Dockerfile'))->toBeTrue();

    // Verify xdebug-toggle.sh was created in .seaman/scripts
    expect(file_exists($this->testDir . '/.seaman/scripts/xdebug-toggle.sh'))->toBeTrue();
});

test('creates .seaman directory if it does not exist', function () {
    $config = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: new PhpConfig(PhpVersion::Php84, new XdebugConfig(false, 'seaman', 'host.docker.internal')),
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
        projectType: ProjectType::Skeleton,
    );

    expect(is_dir($this->testDir . '/.seaman'))->toBeFalse();

    $initializer = new ProjectInitializer($this->registry);
    $initializer->initializeDockerEnvironment($config, $this->testDir);

    expect(is_dir($this->testDir . '/.seaman'))->toBeTrue();
});

test('saves configuration correctly', function () {
    $config = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: new PhpConfig(PhpVersion::Php83, new XdebugConfig(true, 'seaman', 'host.docker.internal')),
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
        projectType: ProjectType::ApiPlatform,
    );

    $initializer = new ProjectInitializer($this->registry);
    $initializer->initializeDockerEnvironment($config, $this->testDir);

    // Verify config can be loaded back
    $configManager = new ConfigManager($this->testDir, $this->registry, new ConfigurationValidator());
    $loadedConfig = $configManager->load();

    expect($loadedConfig->php->version)->toBe(PhpVersion::Php83)
        ->and($loadedConfig->projectType)->toBe(ProjectType::ApiPlatform)
        ->and($loadedConfig->php->xdebug->enabled)->toBeTrue();
});

test('xdebug toggle scripts are executable', function () {
    $config = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: new PhpConfig(PhpVersion::Php84, new XdebugConfig(true, 'seaman', 'host.docker.internal')),
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
        projectType: ProjectType::WebApplication,
    );

    $initializer = new ProjectInitializer($this->registry);
    $initializer->initializeDockerEnvironment($config, $this->testDir);

    $seamanScript = $this->testDir . '/.seaman/scripts/xdebug-toggle.sh';

    expect(is_executable($seamanScript))->toBeTrue();
});

test('does not initialize traefik when proxy disabled', function () {
    $config = new Configuration(
        projectName: 'testproject',
        version: '1.0',
        php: new PhpConfig(PhpVersion::Php84, new XdebugConfig(false, 'seaman', 'host.docker.internal')),
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
        projectType: ProjectType::WebApplication,
        proxy: ProxyConfig::disabled(),
    );

    $initializer = new ProjectInitializer($this->registry);
    $initializer->initializeDockerEnvironment($config, $this->testDir);

    expect(is_dir($this->testDir . '/.seaman/traefik'))->toBeFalse();
    expect(is_dir($this->testDir . '/.seaman/certs'))->toBeFalse();
});

test('initializes traefik when proxy enabled', function () {
    $config = new Configuration(
        projectName: 'testproject',
        version: '1.0',
        php: new PhpConfig(PhpVersion::Php84, new XdebugConfig(false, 'seaman', 'host.docker.internal')),
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
        projectType: ProjectType::WebApplication,
        proxy: ProxyConfig::default('testproject'),
    );

    $initializer = new ProjectInitializer($this->registry);
    $initializer->initializeDockerEnvironment($config, $this->testDir);

    expect(is_dir($this->testDir . '/.seaman/traefik'))->toBeTrue();
    expect(is_dir($this->testDir . '/.seaman/certs'))->toBeTrue();
});

test('creates traefik dynamic certs configuration', function () {
    $config = new Configuration(
        projectName: 'testproject',
        version: '1.0',
        php: new PhpConfig(PhpVersion::Php84, new XdebugConfig(false, 'seaman', 'host.docker.internal')),
        services: new ServiceCollection([]),
        volumes: new VolumeConfig([]),
        projectType: ProjectType::WebApplication,
        proxy: ProxyConfig::default('testproject'),
    );

    $initializer = new ProjectInitializer($this->registry);
    $initializer->initializeDockerEnvironment($config, $this->testDir);

    // Verify certs.yml was created
    $certsYml = $this->testDir . '/.seaman/traefik/dynamic/certs.yml';
    expect(file_exists($certsYml))->toBeTrue();

    // Verify TLS certificate configuration
    $content = file_get_contents($certsYml);
    expect($content)->toContain('/certs/cert.pem')
        ->and($content)->toContain('/certs/key.pem');

    // Verify Traefik dashboard router is configured
    expect($content)->toContain('traefik-dashboard')
        ->and($content)->toContain('traefik.testproject.local')
        ->and($content)->toContain('api@internal');
});
