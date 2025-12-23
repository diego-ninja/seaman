<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Command\Plugin;

use Seaman\Command\Plugin\PluginListCommand;
use Seaman\Plugin\PluginRegistry;
use Symfony\Component\Console\Tester\CommandTester;

test('PluginListCommand shows installed plugins', function (): void {
    $registry = new PluginRegistry();
    $command = new PluginListCommand($registry);
    $tester = new CommandTester($command);

    $tester->execute([]);

    expect($tester->getStatusCode())->toBe(0);
    expect($tester->getDisplay())->toContain('No plugins installed');
});
