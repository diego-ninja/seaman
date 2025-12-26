<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin;

use Seaman\Plugin\PluginRegistry;
use Seaman\Plugin\Loader\LocalPluginLoader;
use Seaman\Plugin\Loader\ComposerPluginLoader;

test('PluginRegistry discovers plugins from loaders', function (): void {
    $fixturesDir = __DIR__ . '/../../Fixtures/Plugins';

    $registry = PluginRegistry::discover(
        projectRoot: sys_get_temp_dir(),
        localPluginsDir: $fixturesDir,
        pluginConfig: [],
    );

    expect($registry->has('valid-plugin'))->toBeTrue();
});

test('PluginRegistry applies config to discovered plugins', function (): void {
    $fixturesDir = __DIR__ . '/../../Fixtures/Plugins';

    $registry = PluginRegistry::discover(
        projectRoot: sys_get_temp_dir(),
        localPluginsDir: $fixturesDir,
        pluginConfig: [
            'valid-plugin' => ['custom' => 'value'],
        ],
    );

    $loaded = $registry->get('valid-plugin');
    expect($loaded->config->get('custom'))->toBe('value');
});
