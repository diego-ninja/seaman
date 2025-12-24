<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Command\Plugin;

use Seaman\Command\Plugin\PluginCreateCommand;
use Symfony\Component\Console\Tester\CommandTester;

test('PluginCreateCommand creates plugin scaffold with src structure', function (): void {
    $tempDir = sys_get_temp_dir() . '/seaman-plugin-test-' . uniqid();
    mkdir($tempDir . '/.seaman', 0755, true);

    $command = new PluginCreateCommand($tempDir);
    $tester = new CommandTester($command);

    $tester->execute(['name' => 'my-plugin']);

    expect($tester->getStatusCode())->toBe(0);
    expect(is_dir($tempDir . '/.seaman/plugins/my-plugin'))->toBeTrue();
    expect(is_dir($tempDir . '/.seaman/plugins/my-plugin/src'))->toBeTrue();
    expect(file_exists($tempDir . '/.seaman/plugins/my-plugin/src/MyPluginPlugin.php'))->toBeTrue();
    expect(is_dir($tempDir . '/.seaman/plugins/my-plugin/templates'))->toBeTrue();

    // Cleanup
    $phpFile = $tempDir . '/.seaman/plugins/my-plugin/src/MyPluginPlugin.php';
    if (file_exists($phpFile)) {
        unlink($phpFile);
    }
    $srcDir = $tempDir . '/.seaman/plugins/my-plugin/src';
    if (is_dir($srcDir)) {
        rmdir($srcDir);
    }
    $templatesDir = $tempDir . '/.seaman/plugins/my-plugin/templates';
    if (is_dir($templatesDir)) {
        rmdir($templatesDir);
    }
    $pluginDir = $tempDir . '/.seaman/plugins/my-plugin';
    if (is_dir($pluginDir)) {
        rmdir($pluginDir);
    }
    $pluginsDir = $tempDir . '/.seaman/plugins';
    if (is_dir($pluginsDir)) {
        rmdir($pluginsDir);
    }
    $seamanDir = $tempDir . '/.seaman';
    if (is_dir($seamanDir)) {
        rmdir($seamanDir);
    }
    if (is_dir($tempDir)) {
        rmdir($tempDir);
    }
});

test('PluginCreateCommand validates plugin name', function (): void {
    $command = new PluginCreateCommand(sys_get_temp_dir());
    $tester = new CommandTester($command);

    $tester->execute(['name' => 'invalid name!']);

    expect($tester->getStatusCode())->toBe(1);
});
