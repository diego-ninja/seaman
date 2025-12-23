<?php

declare(strict_types=1);

// ABOUTME: Tests for ConfigManager service.
// ABOUTME: Validates YAML loading, parsing, and saving.

namespace Seaman\Tests\Unit\Service;

use Seaman\Enum\PhpVersion;
use Seaman\Enum\Service;
use Seaman\Service\ConfigManager;
use Seaman\Service\ConfigurationValidator;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\PhpConfig;
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
    $registry = new ServiceRegistry();
    $validator = new ConfigurationValidator();
    $this->manager = new ConfigManager($this->tempDir, $registry, $validator);
});

afterEach(function () {
    /** @var string $tempDir */
    $tempDir = $this->tempDir;
    if (is_dir($tempDir)) {
        // Remove .seaman directory if it exists
        $seamanDir = $tempDir . '/.seaman';
        if (is_dir($seamanDir)) {
            $seamanFiles = glob($seamanDir . '/*');
            if ($seamanFiles !== false) {
                foreach ($seamanFiles as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            rmdir($seamanDir);
        }

        // Remove all files including hidden files in temp dir
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
    /** @var string $tempDir */
    $tempDir = $this->tempDir;
    $seamanDir = $tempDir . '/.seaman';
    if (!is_dir($seamanDir)) {
        mkdir($seamanDir, 0755, true);
    }
    copy(__DIR__ . '/../../Fixtures/configs/minimal-seaman.yaml', $seamanDir . '/seaman.yaml');

    $config = $manager->load();

    expect($config)->toBeInstanceOf(Configuration::class)
        ->and($config->version)->toBe('1.0')
        ->and($config->php->xdebug->enabled)->toBe(false)
        ->and($config->php->xdebug->ideKey)->toBe('PHPSTORM')
        ->and($config->php->xdebug->clientHost)->toBe('host.docker.internal')
        ->and($config->services->count())->toBe(0)
        ->and($config->volumes->persist)->toBe([])
        ->and($config->plugins)->toBe([]);
});

test('loads configuration with plugins from YAML', function () {
    /** @var ConfigManager $manager */
    $manager = $this->manager;
    /** @var string $tempDir */
    $tempDir = $this->tempDir;
    $seamanDir = $tempDir . '/.seaman';
    if (!is_dir($seamanDir)) {
        mkdir($seamanDir, 0755, true);
    }
    copy(__DIR__ . '/../../Fixtures/configs/plugins-seaman.yaml', $seamanDir . '/seaman.yaml');

    $config = $manager->load();

    expect($config)->toBeInstanceOf(Configuration::class)
        ->and($config->plugins)->toBe([
            'my-plugin' => [
                'setting1' => 'value1',
                'setting2' => 42,
            ],
            'another-plugin' => [
                'enabled' => true,
                'timeout' => 30,
            ],
        ]);
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
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
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

    /** @var ConfigManager $manager */
    $manager = $this->manager;
    $manager->save($config);

    /** @var string $tempDir */
    $tempDir = $this->tempDir;
    $yamlPath = $tempDir . '/.seaman/seaman.yaml';
    expect(file_exists($yamlPath))->toBeTrue();

    $loadedConfig = $manager->load();
    expect($loadedConfig->version)->toBe('1.0')
        ->and($loadedConfig->php->version)->toBe(PhpVersion::Php84);
});

test('saves configuration with plugins to YAML', function () {
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig(PhpVersion::Php84, $xdebug);
    $services = new ServiceCollection([]);
    $volumes = new VolumeConfig([]);
    $plugins = [
        'test-plugin' => [
            'enabled' => true,
            'setting' => 'value',
        ],
    ];

    $config = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: $php,
        services: $services,
        volumes: $volumes,
        plugins: $plugins,
    );

    /** @var ConfigManager $manager */
    $manager = $this->manager;
    $manager->save($config);

    /** @var string $tempDir */
    $tempDir = $this->tempDir;
    $yamlPath = $tempDir . '/.seaman/seaman.yaml';
    expect(file_exists($yamlPath))->toBeTrue();

    $loadedConfig = $manager->load();
    expect($loadedConfig->plugins)->toBe($plugins);
});

test('generates .env file when saving', function () {
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
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
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig(PhpVersion::Php84, $xdebug);
    $existingService = new ServiceConfig('postgresql', true, Service::PostgreSQL, '16', 5432, [], []);
    $services = new ServiceCollection(['postgresql' => $existingService]);
    $volumes = new VolumeConfig(['database']);

    $baseConfig = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: $php,
        services: $services,
        volumes: $volumes,
    );

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
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig(PhpVersion::Php84, $xdebug);
    $services = new ServiceCollection([]);
    $volumes = new VolumeConfig([]);

    $baseConfig = new Configuration(
        projectName: 'test-project',
        version: '1.0',
        php: $php,
        services: $services,
        volumes: $volumes,
    );

    $overrides = [];

    /** @var ConfigManager $manager */
    $manager = $this->manager;
    $merged = $manager->merge($baseConfig, $overrides);

    expect($merged->php->version)->toBe(PhpVersion::Php84);
});

test('generates .env with seaman managed section markers', function () {
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
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

    /** @var ConfigManager $manager */
    $manager = $this->manager;
    $manager->save($config);

    /** @var string $tempDir */
    $tempDir = $this->tempDir;
    $envContent = file_get_contents($tempDir . '/.env');

    expect($envContent)->toContain('# ---- SEAMAN MANAGED ----')
        ->and($envContent)->toContain('# ---- END SEAMAN MANAGED ----');
});

test('preserves user variables when regenerating .env', function () {
    /** @var string $tempDir */
    $tempDir = $this->tempDir;

    // Create existing .env with user variables
    $existingEnv = <<<'ENV'
APP_NAME=MyApp
APP_DEBUG=true
MY_CUSTOM_VAR=custom_value
ENV;
    file_put_contents($tempDir . '/.env', $existingEnv);

    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
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

    /** @var ConfigManager $manager */
    $manager = $this->manager;
    $manager->save($config);

    $envContent = file_get_contents($tempDir . '/.env');

    // User variables should be preserved
    expect($envContent)->toContain('APP_NAME=MyApp')
        ->and($envContent)->toContain('APP_DEBUG=true')
        ->and($envContent)->toContain('MY_CUSTOM_VAR=custom_value')
        // Seaman variables should also be present
        ->and($envContent)->toContain('PHP_VERSION=8.4')
        ->and($envContent)->toContain('XDEBUG_MODE=off');
});

test('preserves APP_PORT from existing .env', function () {
    /** @var string $tempDir */
    $tempDir = $this->tempDir;

    // Create existing .env with custom APP_PORT
    $existingEnv = "APP_PORT=9000\n";
    file_put_contents($tempDir . '/.env', $existingEnv);

    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
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

    /** @var ConfigManager $manager */
    $manager = $this->manager;
    $manager->save($config);

    $envContent = file_get_contents($tempDir . '/.env');

    // APP_PORT should be preserved from existing .env
    expect($envContent)->toContain('APP_PORT=9000');
});

test('does not duplicate variables from seaman section on regenerate', function () {
    /** @var string $tempDir */
    $tempDir = $this->tempDir;

    // Create existing .env with Seaman managed section
    $existingEnv = <<<'ENV'
MY_VAR=value

# ---- SEAMAN MANAGED ----
APP_PORT=8000
PHP_VERSION=8.3
# ---- END SEAMAN MANAGED ----
ENV;
    file_put_contents($tempDir . '/.env', $existingEnv);

    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
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

    /** @var ConfigManager $manager */
    $manager = $this->manager;
    $manager->save($config);

    $envContent = file_get_contents($tempDir . '/.env');

    // MY_VAR should be preserved
    expect($envContent)->toContain('MY_VAR=value')
        // PHP_VERSION should be updated to new value
        ->and($envContent)->toContain('PHP_VERSION=8.4')
        // Old PHP_VERSION should not be present
        ->and($envContent)->not->toContain('PHP_VERSION=8.3');
});
