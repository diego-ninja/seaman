<?php

declare(strict_types=1);

// ABOUTME: Tests for ConfigManager service.
// ABOUTME: Validates YAML loading, parsing, and saving.

namespace Seaman\Tests\Unit\Service;

use Seaman\Service\ConfigManager;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\ServerConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\VolumeConfig;
use Seaman\ValueObject\XdebugConfig;

/**
 * @property string $tempDir
 * @property ConfigManager $manager
 */
beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/seaman-test-' . uniqid();
    mkdir($this->tempDir, 0755, true);
    $this->manager = new ConfigManager($this->tempDir);
});

afterEach(function () {
    /** @var string $tempDir */
    $tempDir = $this->tempDir;
    if (is_dir($tempDir)) {
        // Remove all files including hidden files
        $files = glob($tempDir . '/{,.}*', GLOB_BRACE);
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        rmdir($tempDir);
    }
});

test('loads configuration from YAML', function () {
    /** @var ConfigManager $manager */
    $manager = $this->manager;
    copy(__DIR__ . '/../../Fixtures/configs/minimal-seaman.yaml', $this->tempDir . '/seaman.yaml');

    $config = $manager->load();

    expect($config)->toBeInstanceOf(Configuration::class)
        ->and($config->version)->toBe('1.0')
        ->and($config->server->type)->toBe('symfony')
        ->and($config->server->port)->toBe(8000)
        ->and($config->php->version)->toBe('8.4')
        ->and($config->php->extensions)->toBe(['pdo_pgsql', 'redis'])
        ->and($config->php->xdebug->enabled)->toBe(false)
        ->and($config->php->xdebug->ideKey)->toBe('PHPSTORM')
        ->and($config->php->xdebug->clientHost)->toBe('host.docker.internal')
        ->and($config->services->count())->toBe(0)
        ->and($config->volumes->persist)->toBe([]);
});

test('throws exception when seaman.yaml does not exist', function () {
    /** @var ConfigManager $manager */
    $manager = $this->manager;
    expect(fn() => $manager->load())
        ->toThrow(\RuntimeException::class, 'seaman.yaml not found');
});

test('throws exception when YAML is invalid', function () {
    /** @var string $tempDir */
    $tempDir = $this->tempDir;
    /** @var ConfigManager $manager */
    $manager = $this->manager;

    $yamlPath = $tempDir . '/seaman.yaml';
    file_put_contents($yamlPath, "invalid: yaml: content: [");

    expect(fn() => $manager->load())
        ->toThrow(\RuntimeException::class);
});

test('saves configuration to YAML', function () {
    $server = new ServerConfig('symfony', 8000);
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig('8.4', ['pdo_pgsql'], $xdebug);
    $services = new ServiceCollection([]);
    $volumes = new VolumeConfig([]);

    $config = new Configuration('1.0', $server, $php, $services, $volumes);

    /** @var ConfigManager $manager */
    $manager = $this->manager;
    $manager->save($config);

    /** @var string $tempDir */
    $tempDir = $this->tempDir;
    $yamlPath = $tempDir . '/seaman.yaml';
    expect(file_exists($yamlPath))->toBeTrue();

    $loadedConfig = $manager->load();
    expect($loadedConfig->version)->toBe('1.0')
        ->and($loadedConfig->server->type)->toBe('symfony')
        ->and($loadedConfig->php->version)->toBe('8.4');
});

test('generates .env file when saving', function () {
    $server = new ServerConfig('symfony', 8000);
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig('8.4', [], $xdebug);
    $services = new ServiceCollection([]);
    $volumes = new VolumeConfig([]);

    $config = new Configuration('1.0', $server, $php, $services, $volumes);

    /** @var ConfigManager $manager */
    $manager = $this->manager;
    $manager->save($config);

    /** @var string $tempDir */
    $tempDir = $this->tempDir;
    $envPath = $tempDir . '/.env';
    expect(file_exists($envPath))->toBeTrue();

    $envContent = file_get_contents($envPath);
    expect($envContent)->toContain('APP_PORT=8000')
        ->and($envContent)->toContain('PHP_VERSION=8.4')
        ->and($envContent)->toContain('XDEBUG_MODE=off');
});

test('merges service into existing configuration', function () {
    $server = new ServerConfig('symfony', 8000);
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig('8.4', [], $xdebug);
    $existingService = new ServiceConfig('postgresql', true, 'postgresql', '16', 5432, [], []);
    $services = new ServiceCollection(['postgresql' => $existingService]);
    $volumes = new VolumeConfig(['database']);

    $baseConfig = new Configuration('1.0', $server, $php, $services, $volumes);

    $overrides = [
        'services' => [
            'redis' => [
                'enabled' => true,
                'type' => 'redis',
                'version' => '7-alpine',
                'port' => 6379,
            ],
        ],
        'volumes' => [
            'persist' => ['database', 'redis'],
        ],
    ];

    /** @var ConfigManager $manager */
    $manager = $this->manager;
    $merged = $manager->merge($baseConfig, $overrides);

    expect($merged->services->count())->toBe(2)
        ->and($merged->services->has('postgresql'))->toBeTrue()
        ->and($merged->services->has('redis'))->toBeTrue()
        ->and($merged->volumes->persist)->toBe(['database', 'redis']);
});

test('merge preserves existing configuration', function () {
    $server = new ServerConfig('symfony', 8000);
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig('8.4', ['pdo_pgsql'], $xdebug);
    $services = new ServiceCollection([]);
    $volumes = new VolumeConfig([]);

    $baseConfig = new Configuration('1.0', $server, $php, $services, $volumes);

    $overrides = [
        'server' => [
            'port' => 9000,
        ],
    ];

    /** @var ConfigManager $manager */
    $manager = $this->manager;
    $merged = $manager->merge($baseConfig, $overrides);

    expect($merged->server->port)->toBe(9000)
        ->and($merged->server->type)->toBe('symfony')
        ->and($merged->php->version)->toBe('8.4');
});
