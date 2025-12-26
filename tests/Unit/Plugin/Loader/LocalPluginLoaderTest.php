<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Loader;

use Seaman\Plugin\Loader\LocalPluginLoader;
use Seaman\Plugin\PluginInterface;

test('LocalPluginLoader discovers plugins in directory', function (): void {
    $loader = new LocalPluginLoader(__DIR__ . '/../../../Fixtures/Plugins');
    $plugins = $loader->load();

    expect($plugins)->toHaveCount(1);
    expect($plugins[0])->toBeInstanceOf(PluginInterface::class);
    expect($plugins[0]->getName())->toBe('valid-plugin');
});

test('LocalPluginLoader returns empty array for non-existent directory', function (): void {
    $loader = new LocalPluginLoader('/non/existent/path');
    $plugins = $loader->load();

    expect($plugins)->toBe([]);
});

test('LocalPluginLoader ignores classes without AsSeamanPlugin attribute', function (): void {
    $tempDir = sys_get_temp_dir() . '/seaman-test-' . uniqid();
    mkdir($tempDir, 0755, true);
    file_put_contents($tempDir . '/NotAPlugin.php', '<?php class NotAPlugin {}');

    $loader = new LocalPluginLoader($tempDir);
    $plugins = $loader->load();

    expect($plugins)->toBe([]);

    unlink($tempDir . '/NotAPlugin.php');
    rmdir($tempDir);
});
