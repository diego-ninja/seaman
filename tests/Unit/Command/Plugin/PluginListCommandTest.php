<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Command\Plugin;

use Seaman\Command\Plugin\PluginListCommand;
use Seaman\Plugin\PluginRegistry;
use Seaman\Service\PackagistClient;
use Symfony\Component\Console\Tester\CommandTester;

test('PluginListCommand shows installed plugins', function (): void {
    $registry = new PluginRegistry();
    $packagist = new PackagistClient();
    $command = new PluginListCommand($registry, $packagist);
    $tester = new CommandTester($command);

    $tester->execute(['--installed' => true]);

    expect($tester->getStatusCode())->toBe(0);
    expect($tester->getDisplay())->toContain('No plugins installed');
});

test('PluginListCommand can filter to show only installed plugins', function (): void {
    $registry = new PluginRegistry();
    $packagist = new PackagistClient();
    $command = new PluginListCommand($registry, $packagist);
    $tester = new CommandTester($command);

    $tester->execute(['--installed' => true]);

    expect($tester->getStatusCode())->toBe(0);
    $display = $tester->getDisplay();
    expect($display)->toContain('Installed plugins');
    expect($display)->not->toContain('Available plugins');
});
