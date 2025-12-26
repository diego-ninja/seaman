<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin;

use Seaman\Plugin\PluginRegistry;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\LoadedPlugin;
use Seaman\Plugin\Attribute\AsSeamanPlugin;

test('PluginRegistry can register and retrieve plugins', function (): void {
    $registry = new PluginRegistry();

    $plugin = new #[AsSeamanPlugin(name: 'test')] class implements PluginInterface {
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
            return 'Test';
        }
    };

    $registry->register($plugin, []);

    expect($registry->has('test'))->toBeTrue();
    expect($registry->get('test'))->toBeInstanceOf(LoadedPlugin::class);
});

test('PluginRegistry returns all registered plugins', function (): void {
    $registry = new PluginRegistry();

    $plugin1 = new #[AsSeamanPlugin(name: 'plugin-1')] class implements PluginInterface {
        public function getName(): string
        {
            return 'plugin-1';
        }
        public function getVersion(): string
        {
            return '1.0.0';
        }
        public function getDescription(): string
        {
            return 'Plugin 1';
        }
    };

    $plugin2 = new #[AsSeamanPlugin(name: 'plugin-2')] class implements PluginInterface {
        public function getName(): string
        {
            return 'plugin-2';
        }
        public function getVersion(): string
        {
            return '1.0.0';
        }
        public function getDescription(): string
        {
            return 'Plugin 2';
        }
    };

    $registry->register($plugin1, []);
    $registry->register($plugin2, []);

    expect($registry->all())->toHaveCount(2);
});

test('PluginRegistry throws for unknown plugin', function (): void {
    $registry = new PluginRegistry();

    $registry->get('unknown');
})->throws(\InvalidArgumentException::class);
