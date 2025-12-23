<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Loader;

use Seaman\Plugin\Loader\ComposerPluginLoader;

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
