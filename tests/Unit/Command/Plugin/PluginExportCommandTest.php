<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Command\Plugin;

use Seaman\Command\Plugin\PluginExportCommand;
use Seaman\Plugin\Export\PluginExporter;
use Symfony\Component\Console\Tester\CommandTester;

test('PluginExportCommand fails when plugin name is invalid', function (): void {
    $tempDir = sys_get_temp_dir() . '/seaman-export-test-' . uniqid();
    mkdir($tempDir . '/.seaman/plugins', 0755, true);

    $exporter = createMockExporter();
    $command = new PluginExportCommand($tempDir, $exporter);
    $tester = new CommandTester($command);

    $tester->execute(['plugin-name' => 'NonExistent']);

    expect($tester->getStatusCode())->toBe(1);
    expect($tester->getDisplay())->toContain('not found');

    recursiveRemove($tempDir);
});

test('PluginExportCommand fails when plugin has no src directory', function (): void {
    $tempDir = sys_get_temp_dir() . '/seaman-export-test-' . uniqid();
    $pluginDir = $tempDir . '/.seaman/plugins/my-plugin';
    mkdir($pluginDir, 0755, true);

    $exporter = createMockExporter();
    $command = new PluginExportCommand($tempDir, $exporter);
    $tester = new CommandTester($command);

    $tester->execute(['plugin-name' => 'my-plugin']);

    expect($tester->getStatusCode())->toBe(1);
    expect($tester->getDisplay())->toContain('src/');

    recursiveRemove($tempDir);
});

test('PluginExportCommand fails when plugin has no PHP files with AsSeamanPlugin attribute', function (): void {
    $tempDir = sys_get_temp_dir() . '/seaman-export-test-' . uniqid();
    $pluginDir = $tempDir . '/.seaman/plugins/my-plugin';
    mkdir($pluginDir . '/src', 0755, true);

    file_put_contents($pluginDir . '/src/Empty.php', '<?php');

    $exporter = createMockExporter();
    $command = new PluginExportCommand($tempDir, $exporter);
    $tester = new CommandTester($command);

    $tester->execute(['plugin-name' => 'my-plugin']);

    expect($tester->getStatusCode())->toBe(1);
    expect($tester->getDisplay())->toContain('AsSeamanPlugin');

    recursiveRemove($tempDir);
});

test('PluginExportCommand accepts valid plugin with proper structure', function (): void {
    $tempDir = sys_get_temp_dir() . '/seaman-export-test-' . uniqid();
    $pluginDir = $tempDir . '/.seaman/plugins/my-plugin';
    mkdir($pluginDir . '/src', 0755, true);

    $content = <<<'PHP'
<?php

declare(strict_types=1);

namespace Seaman\LocalPlugins\MyPlugin;

use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\PluginInterface;

#[AsSeamanPlugin(
    name: 'my-plugin',
    version: '1.0.0',
    description: 'Test plugin',
)]
final class MyPluginPlugin implements PluginInterface
{
    public function getName(): string
    {
        return 'my-plugin';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'Test plugin';
    }
}
PHP;

    file_put_contents($pluginDir . '/src/MyPluginPlugin.php', $content);

    $exporter = createMockExporter();
    $command = new PluginExportCommand($tempDir, $exporter);
    $tester = new CommandTester($command);

    $tester->execute([
        'plugin-name' => 'my-plugin',
        '--vendor' => 'test-vendor',
        '--output' => $tempDir . '/exports',
    ]);

    expect($tester->getStatusCode())->toBe(0);

    recursiveRemove($tempDir);
});

test('PluginExportCommand uses default output directory when not specified', function (): void {
    $tempDir = sys_get_temp_dir() . '/seaman-export-test-' . uniqid();
    $pluginDir = $tempDir . '/.seaman/plugins/my-plugin';
    mkdir($pluginDir . '/src', 0755, true);

    $content = <<<'PHP'
<?php

declare(strict_types=1);

namespace Seaman\LocalPlugins\MyPlugin;

use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\PluginInterface;

#[AsSeamanPlugin(
    name: 'my-plugin',
    version: '1.0.0',
)]
final class MyPluginPlugin implements PluginInterface
{
    public function getName(): string { return 'my-plugin'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getDescription(): string { return ''; }
}
PHP;

    file_put_contents($pluginDir . '/src/MyPluginPlugin.php', $content);

    $exporter = createMockExporter();
    $command = new PluginExportCommand($tempDir, $exporter);
    $tester = new CommandTester($command);

    $tester->execute([
        'plugin-name' => 'my-plugin',
        '--vendor' => 'test-vendor',
    ]);

    expect($tester->getStatusCode())->toBe(0);

    recursiveRemove($tempDir);
});

/**
 * Helper function to create a mock exporter.
 *
 * @return PluginExporter
 */
function createMockExporter(): PluginExporter
{
    return new class implements PluginExporter {
        public function export(string $pluginPath, string $outputPath, string $vendorName): void {}
    };
}

/**
 * Helper function for cleanup.
 */
function recursiveRemove(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $files = array_diff(scandir($dir) ?: [], ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            recursiveRemove($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}
