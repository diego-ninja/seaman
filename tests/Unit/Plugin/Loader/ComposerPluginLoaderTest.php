<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Loader;

use Seaman\Plugin\Loader\ComposerPluginLoader;
use Seaman\Plugin\Loader\PluginAutoloader;

beforeEach(function (): void {
    PluginAutoloader::resetForTesting();
});

test('ComposerPluginLoader returns empty array when no plugins installed', function (): void {
    $tempDir = sys_get_temp_dir() . '/seaman-composer-test-' . uniqid();
    mkdir($tempDir . '/vendor/composer', 0755, true);
    file_put_contents($tempDir . '/vendor/composer/installed.json', json_encode(['packages' => []]));

    $loader = new ComposerPluginLoader($tempDir);
    $plugins = $loader->load();

    expect($plugins)->toBe([]);

    unlink($tempDir . '/vendor/composer/installed.json');
    rmdir($tempDir . '/vendor/composer');
    rmdir($tempDir . '/vendor');
    rmdir($tempDir);
});

test('ComposerPluginLoader returns empty for missing vendor directory', function (): void {
    $loader = new ComposerPluginLoader('/non/existent/path');
    $plugins = $loader->load();

    expect($plugins)->toBe([]);
});

test('ComposerPluginLoader registers autoloader for plugin dependencies', function (): void {
    // Create a mock project structure with a seaman-plugin
    $projectRoot = sys_get_temp_dir() . '/composer-loader-test-' . uniqid();
    mkdir($projectRoot . '/vendor/composer', 0777, true);
    mkdir($projectRoot . '/vendor/acme/test-plugin/src', 0777, true);

    // Generate unique class name to avoid conflicts
    $uniqueId = uniqid();
    $className = "Acme\\TestPlugin{$uniqueId}\\TestPlugin";
    $namespace = "Acme\\TestPlugin{$uniqueId}";

    // Create installed.json
    $installed = [
        'packages' => [
            [
                'name' => 'acme/test-plugin',
                'type' => 'seaman-plugin',
                'install-path' => '../acme/test-plugin',
                'autoload' => [
                    'psr-4' => [
                        $namespace . '\\' => 'src/',
                    ],
                ],
                'extra' => [
                    'seaman' => [
                        'plugin-class' => $className,
                    ],
                ],
            ],
        ],
    ];
    file_put_contents(
        $projectRoot . '/vendor/composer/installed.json',
        json_encode($installed),
    );

    // Create plugin class that implements PluginInterface
    $pluginCode = <<<PHP
<?php
declare(strict_types=1);

namespace {$namespace};

use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\Attribute\AsSeamanPlugin;

#[AsSeamanPlugin(name: 'test', version: '1.0.0', description: 'Test plugin')]
final class TestPlugin implements PluginInterface
{
    public function getName(): string { return 'test'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getDescription(): string { return 'Test plugin'; }
}
PHP;
    file_put_contents(
        $projectRoot . '/vendor/acme/test-plugin/src/TestPlugin.php',
        $pluginCode,
    );

    $loader = new \Seaman\Plugin\Loader\ComposerPluginLoader($projectRoot);
    $plugins = $loader->load();

    expect($plugins)->toHaveCount(1);
    expect($plugins[0]->getName())->toBe('test');

    // Cleanup
    unlink($projectRoot . '/vendor/acme/test-plugin/src/TestPlugin.php');
    rmdir($projectRoot . '/vendor/acme/test-plugin/src');
    rmdir($projectRoot . '/vendor/acme/test-plugin');
    rmdir($projectRoot . '/vendor/acme');
    unlink($projectRoot . '/vendor/composer/installed.json');
    rmdir($projectRoot . '/vendor/composer');
    rmdir($projectRoot . '/vendor');
    rmdir($projectRoot);
});
