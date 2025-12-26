<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Loader;

use Seaman\Plugin\Loader\PluginAutoloader;

beforeEach(function (): void {
    PluginAutoloader::resetForTesting();
});

test('loadClass returns false when no mappings registered', function (): void {
    $autoloader = new PluginAutoloader();

    $result = $autoloader->loadClass('NonExistent\\SomeClass');

    expect($result)->toBeFalse();
});

test('loadClass resolves class from added mappings', function (): void {
    $autoloader = new PluginAutoloader();

    // Create temp directory with a test class
    $tempDir = sys_get_temp_dir() . '/plugin-autoloader-test-' . uniqid();
    mkdir($tempDir . '/src', 0777, true);

    $className = 'TestVendor' . uniqid() . '\\TestPlugin\\TestPlugin';
    $namespace = substr($className, 0, (int) strrpos($className, '\\') + 1);
    $shortClass = substr($className, (int) strrpos($className, '\\') + 1);

    file_put_contents(
        $tempDir . '/src/' . $shortClass . '.php',
        "<?php namespace " . rtrim($namespace, '\\') . "; class {$shortClass} {}",
    );

    $package = [
        'name' => 'test-vendor/test-plugin',
        'install-path' => $tempDir,
        'autoload' => [
            'psr-4' => [
                $namespace => 'src/',
            ],
        ],
    ];

    $autoloader->addPackageMappings($package, '');

    $result = $autoloader->loadClass($className);

    expect($result)->toBeTrue();
    expect(class_exists($className, false))->toBeTrue();

    // Cleanup
    unlink($tempDir . '/src/' . $shortClass . '.php');
    rmdir($tempDir . '/src');
    rmdir($tempDir);
});

test('resolveWithDependencies includes transitive dependencies', function (): void {
    $autoloader = new PluginAutoloader();

    $packages = [
        [
            'name' => 'acme/seaman-redis',
            'require' => ['predis/predis' => '^2.0'],
            'autoload' => ['psr-4' => ['Acme\\Redis\\' => 'src/']],
            'install-path' => '../acme/seaman-redis',
        ],
        [
            'name' => 'predis/predis',
            'require' => ['php' => '>=8.1'],
            'autoload' => ['psr-4' => ['Predis\\' => 'src/']],
            'install-path' => '../predis/predis',
        ],
        [
            'name' => 'unrelated/package',
            'autoload' => ['psr-4' => ['Unrelated\\' => 'src/']],
            'install-path' => '../unrelated/package',
        ],
    ];

    $resolved = $autoloader->resolveWithDependencies(
        ['acme/seaman-redis'],
        $packages,
    );

    $resolvedNames = array_column($resolved, 'name');

    expect($resolvedNames)->toContain('acme/seaman-redis');
    expect($resolvedNames)->toContain('predis/predis');
    expect($resolvedNames)->not->toContain('unrelated/package');
});

test('resolveWithDependencies ignores platform dependencies', function (): void {
    $autoloader = new PluginAutoloader();

    $packages = [
        [
            'name' => 'acme/seaman-redis',
            'require' => [
                'php' => '>=8.1',
                'ext-json' => '*',
                'lib-pcre' => '*',
            ],
            'autoload' => ['psr-4' => ['Acme\\Redis\\' => 'src/']],
            'install-path' => '../acme/seaman-redis',
        ],
    ];

    $resolved = $autoloader->resolveWithDependencies(
        ['acme/seaman-redis'],
        $packages,
    );

    expect($resolved)->toHaveCount(1);
    expect($resolved[0]['name'])->toBe('acme/seaman-redis');
});

test('register only registers once across instances', function (): void {
    $autoloader1 = new PluginAutoloader();
    $autoloader2 = new PluginAutoloader();

    $packages = [
        [
            'name' => 'acme/plugin',
            'autoload' => ['psr-4' => ['Acme\\' => 'src/']],
            'install-path' => '../acme/plugin',
        ],
    ];

    $autoloader1->register('/tmp/project', ['acme/plugin'], $packages);
    $autoloader2->register('/tmp/project', ['acme/plugin'], $packages);

    expect(PluginAutoloader::isRegistered())->toBeTrue();
});

test('register does nothing when no plugins', function (): void {
    $autoloader = new PluginAutoloader();

    $autoloader->register('/tmp/project', [], []);

    expect(PluginAutoloader::isRegistered())->toBeFalse();
});
