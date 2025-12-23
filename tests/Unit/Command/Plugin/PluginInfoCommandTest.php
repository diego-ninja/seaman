<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Command\Plugin;

use Seaman\Command\Plugin\PluginInfoCommand;
use Seaman\Plugin\PluginRegistry;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Symfony\Component\Console\Tester\CommandTester;

test('PluginInfoCommand shows plugin details', function (): void {
    $registry = new PluginRegistry();

    $plugin = new #[AsSeamanPlugin(name: 'test-plugin', version: '2.0.0', description: 'A test plugin')]
    class implements PluginInterface {
        public function getName(): string
        {
            return 'test-plugin';
        }
        public function getVersion(): string
        {
            return '2.0.0';
        }
        public function getDescription(): string
        {
            return 'A test plugin';
        }
    };

    $registry->register($plugin, [], 'composer');

    $command = new PluginInfoCommand($registry);
    $tester = new CommandTester($command);

    $tester->execute(['name' => 'test-plugin']);

    expect($tester->getStatusCode())->toBe(0);
    expect($tester->getDisplay())->toContain('test-plugin');
    expect($tester->getDisplay())->toContain('2.0.0');
});

test('PluginInfoCommand fails for unknown plugin', function (): void {
    $registry = new PluginRegistry();
    $command = new PluginInfoCommand($registry);
    $tester = new CommandTester($command);

    $tester->execute(['name' => 'unknown']);

    expect($tester->getStatusCode())->toBe(1);
});
