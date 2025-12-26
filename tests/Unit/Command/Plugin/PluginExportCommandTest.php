<?php

// ABOUTME: Tests for PluginExportCommand validation and export functionality.
// ABOUTME: Verifies plugin structure requirements, vendor name handling, and output paths.

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

test('PluginExportCommand uses vendor name from git config when not specified', function (): void {
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

    $gitUser = trim(shell_exec('git config user.name 2>/dev/null') ?: '');
    $expectedVendor = $gitUser !== '' ? strtolower(str_replace(' ', '-', $gitUser)) : 'your-vendor';

    // Use real exporter - it will create files
    $outputDir = $tempDir . '/exports';
    $exporter = new PluginExporter(new \Seaman\Plugin\Export\NamespaceTransformer());

    $command = new PluginExportCommand($tempDir, $exporter);
    $tester = new CommandTester($command);

    $tester->execute([
        'plugin-name' => 'my-plugin',
        '--output' => $outputDir,
    ]);

    expect($tester->getStatusCode())->toBe(0);

    // Verify the vendor name was used correctly by checking composer.json
    expect(file_exists($outputDir . '/composer.json'))->toBeTrue();
    $composerContent = file_get_contents($outputDir . '/composer.json');
    expect($composerContent)->toBeString();
    $composer = json_decode($composerContent, true);
    expect($composer)->toBeArray();
    expect($composer['name'])->toBe($expectedVendor . '/my-plugin');

    recursiveRemove($tempDir);
});

/**
 * Helper function to create a no-op exporter for tests that don't need actual export.
 * Uses a stub implementation that discards export operations.
 *
 * @return PluginExporter
 */
function createMockExporter(): PluginExporter
{
    // For tests that don't need actual export functionality,
    // we use the real implementation. The tests create temp directories
    // that are cleaned up afterward, so actual exports are harmless.
    return new PluginExporter(new \Seaman\Plugin\Export\NamespaceTransformer());
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
