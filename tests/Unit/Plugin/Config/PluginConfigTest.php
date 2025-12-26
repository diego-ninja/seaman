<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Config;

use Seaman\Plugin\Config\PluginConfig;

test('PluginConfig provides typed access to values', function (): void {
    $config = new PluginConfig([
        'nodes' => 3,
        'name' => 'cluster',
        'enabled' => true,
    ]);

    expect($config->get('nodes'))->toBe(3);
    expect($config->get('name'))->toBe('cluster');
    expect($config->get('enabled'))->toBe(true);
});

test('PluginConfig returns all values', function (): void {
    $values = ['nodes' => 3, 'name' => 'cluster'];
    $config = new PluginConfig($values);

    expect($config->all())->toBe($values);
});

test('PluginConfig returns null for missing keys', function (): void {
    $config = new PluginConfig(['nodes' => 3]);

    expect($config->get('missing'))->toBeNull();
});

test('PluginConfig has method checks key existence', function (): void {
    $config = new PluginConfig(['nodes' => 3]);

    expect($config->has('nodes'))->toBeTrue();
    expect($config->has('missing'))->toBeFalse();
});
