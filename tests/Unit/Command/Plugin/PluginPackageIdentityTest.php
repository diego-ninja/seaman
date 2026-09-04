<?php

declare(strict_types=1);

// ABOUTME: Tests Composer package identity in plugin management commands.
// ABOUTME: Ensures logical plugin names are not passed to Composer.

namespace Seaman\Tests\Unit\Command\Plugin;

use Seaman\Command\Plugin\PluginInstallCommand;
use Seaman\Command\Plugin\PluginRemoveCommand;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\PluginRegistry;
use Seaman\Service\PackagistClient;

test('plugin commands use Composer package identity', function (): void {
    $plugin = new class implements PluginInterface {
        public function getName(): string
        {
            return 'friendly-plugin';
        }

        public function getVersion(): string
        {
            return '1.0.0';
        }

        public function getDescription(): string
        {
            return 'Friendly plugin';
        }
    };

    $registry = new PluginRegistry();
    $registry->register($plugin, [], 'composer', 'acme/seaman-plugin');

    $installCommand = new PluginInstallCommand(new PackagistClient(), $registry, sys_get_temp_dir());
    $installedNames = new \ReflectionMethod($installCommand, 'getInstalledPluginNames')->invoke($installCommand);

    $removeCommand = new PluginRemoveCommand($registry, sys_get_temp_dir());
    $isInstalled = new \ReflectionMethod($removeCommand, 'isComposerInstalled')->invoke(
        $removeCommand,
        'acme/seaman-plugin',
    );
    $removable = new \ReflectionMethod($removeCommand, 'getComposerInstalledPlugins')->invoke($removeCommand);

    expect($installedNames)->toBe(['acme/seaman-plugin'])
        ->and($isInstalled)->toBeTrue()
        ->and($removable)->toBe([[
            'name' => 'acme/seaman-plugin',
            'description' => 'Friendly plugin',
        ]]);
});
