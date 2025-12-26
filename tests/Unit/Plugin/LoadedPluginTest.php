<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin;

use Seaman\Plugin\LoadedPlugin;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\Config\PluginConfig;

test('LoadedPlugin stores plugin instance and metadata', function (): void {
    $plugin = new class implements PluginInterface {
        public function getName(): string
        {
            return 'test';
        }
        public function getVersion(): string
        {
            return '1.0.0';
        }
        public function getDescription(): string
        {
            return 'Test plugin';
        }
    };

    $config = new PluginConfig(['nodes' => 3]);
    $loaded = new LoadedPlugin(
        instance: $plugin,
        config: $config,
        source: 'composer',
    );

    expect($loaded->instance)->toBe($plugin);
    expect($loaded->config)->toBe($config);
    expect($loaded->source)->toBe('composer');
});
