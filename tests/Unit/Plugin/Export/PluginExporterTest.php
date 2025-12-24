<?php

// ABOUTME: Tests for PluginExporter service.
// ABOUTME: Verifies plugin export to distributable Composer packages.

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Export;

use PHPUnit\Framework\TestCase;
use Seaman\Plugin\Export\DefaultPluginExporter;
use Seaman\Plugin\Export\NamespaceTransformer;

final class PluginExporterTest extends TestCase
{
    private string $tempPluginDir;
    private string $tempOutputDir;
    private DefaultPluginExporter $exporter;

    protected function setUp(): void
    {
        $this->tempPluginDir = sys_get_temp_dir() . '/seaman-test-plugin-' . uniqid();
        $this->tempOutputDir = sys_get_temp_dir() . '/seaman-test-output-' . uniqid();

        mkdir($this->tempPluginDir);
        mkdir($this->tempOutputDir);

        $this->exporter = new DefaultPluginExporter(new NamespaceTransformer());
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempPluginDir);
        $this->removeDirectory($this->tempOutputDir);
    }

    public function test_exports_plugin_with_basic_structure(): void
    {
        // Create plugin structure
        $this->createPluginStructure();

        // Export
        $this->exporter->export(
            $this->tempPluginDir,
            $this->tempOutputDir,
            'diego',
        );

        // Verify src directory was copied
        $this->assertDirectoryExists($this->tempOutputDir . '/src');
        $this->assertFileExists($this->tempOutputDir . '/src/MyPlugin.php');

        // Verify namespace was transformed
        $content = file_get_contents($this->tempOutputDir . '/src/MyPlugin.php');
        $this->assertIsString($content);
        $this->assertStringContainsString('namespace Diego\MyPlugin;', $content);
        $this->assertStringNotContainsString('Seaman\LocalPlugins\MyPlugin', $content);
    }

    public function test_exports_plugin_with_subdirectories(): void
    {
        // Create plugin with subdirectories
        $this->createPluginStructure();
        mkdir($this->tempPluginDir . '/src/Command');
        file_put_contents(
            $this->tempPluginDir . '/src/Command/TestCommand.php',
            $this->getCommandFileContent(),
        );

        // Export
        $this->exporter->export(
            $this->tempPluginDir,
            $this->tempOutputDir,
            'diego',
        );

        // Verify structure
        $this->assertDirectoryExists($this->tempOutputDir . '/src/Command');
        $this->assertFileExists($this->tempOutputDir . '/src/Command/TestCommand.php');

        // Verify namespace transformation
        $content = file_get_contents($this->tempOutputDir . '/src/Command/TestCommand.php');
        $this->assertIsString($content);
        $this->assertStringContainsString('namespace Diego\MyPlugin\Command;', $content);
    }

    public function test_copies_templates_directory_unchanged(): void
    {
        $this->createPluginStructure();
        mkdir($this->tempPluginDir . '/templates');
        file_put_contents(
            $this->tempPluginDir . '/templates/test.yaml.twig',
            'service: {{ name }}',
        );

        // Export
        $this->exporter->export(
            $this->tempPluginDir,
            $this->tempOutputDir,
            'diego',
        );

        // Verify templates copied
        $this->assertDirectoryExists($this->tempOutputDir . '/templates');
        $this->assertFileExists($this->tempOutputDir . '/templates/test.yaml.twig');
        $this->assertSame(
            'service: {{ name }}',
            file_get_contents($this->tempOutputDir . '/templates/test.yaml.twig'),
        );
    }

    public function test_generates_composer_json_with_metadata(): void
    {
        $this->createPluginStructure();

        // Export
        $this->exporter->export(
            $this->tempPluginDir,
            $this->tempOutputDir,
            'diego',
        );

        // Verify composer.json exists
        $this->assertFileExists($this->tempOutputDir . '/composer.json');

        // Parse and verify structure
        $composerContent = file_get_contents($this->tempOutputDir . '/composer.json');
        $this->assertIsString($composerContent);

        $composer = json_decode($composerContent, true);
        $this->assertIsArray($composer);

        $this->assertArrayHasKey('name', $composer);
        $this->assertSame('diego/my-plugin', $composer['name']);

        $this->assertArrayHasKey('description', $composer);
        $this->assertSame('Test plugin description', $composer['description']);

        $this->assertArrayHasKey('type', $composer);
        $this->assertSame('seaman-plugin', $composer['type']);

        $this->assertArrayHasKey('license', $composer);
        $this->assertSame('MIT', $composer['license']);

        $this->assertArrayHasKey('require', $composer);
        $this->assertIsArray($composer['require']);
        $this->assertArrayHasKey('php', $composer['require']);
        $this->assertSame('^8.4', $composer['require']['php']);

        $this->assertArrayHasKey('require-dev', $composer);
        $this->assertIsArray($composer['require-dev']);
        $this->assertArrayHasKey('seaman/seaman', $composer['require-dev']);
        $this->assertSame('^1.0', $composer['require-dev']['seaman/seaman']);

        $this->assertArrayHasKey('autoload', $composer);
        $this->assertIsArray($composer['autoload']);
        $this->assertArrayHasKey('psr-4', $composer['autoload']);
        $this->assertIsArray($composer['autoload']['psr-4']);
        $this->assertSame(['Diego\\MyPlugin\\' => 'src/'], $composer['autoload']['psr-4']);

        $this->assertArrayHasKey('extra', $composer);
        $this->assertIsArray($composer['extra']);
        $this->assertArrayHasKey('seaman', $composer['extra']);
        $this->assertIsArray($composer['extra']['seaman']);
        $this->assertArrayHasKey('plugin-class', $composer['extra']['seaman']);
        $this->assertSame('Diego\\MyPlugin\\MyPlugin', $composer['extra']['seaman']['plugin-class']);
    }

    public function test_throws_exception_when_plugin_attribute_not_found(): void
    {
        mkdir($this->tempPluginDir . '/src');
        file_put_contents(
            $this->tempPluginDir . '/src/MyPlugin.php',
            '<?php class MyPlugin {}',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not find AsSeamanPlugin attribute');

        $this->exporter->export(
            $this->tempPluginDir,
            $this->tempOutputDir,
            'diego',
        );
    }

    public function test_throws_exception_when_src_directory_missing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Plugin must have a src directory');

        $this->exporter->export(
            $this->tempPluginDir,
            $this->tempOutputDir,
            'diego',
        );
    }

    private function createPluginStructure(): void
    {
        mkdir($this->tempPluginDir . '/src');
        file_put_contents(
            $this->tempPluginDir . '/src/MyPlugin.php',
            $this->getMainPluginFileContent(),
        );
    }

    private function getMainPluginFileContent(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace Seaman\LocalPlugins\MyPlugin;

use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\PluginInterface;

#[AsSeamanPlugin(
    name: 'my-plugin',
    version: '1.0.0',
    description: 'Test plugin description'
)]
final class MyPlugin implements PluginInterface
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
        return 'Test plugin description';
    }
}
PHP;
    }

    private function getCommandFileContent(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace Seaman\LocalPlugins\MyPlugin\Command;

use Seaman\LocalPlugins\MyPlugin\MyPlugin;

class TestCommand
{
    public function __construct(
        private MyPlugin $plugin
    ) {}
}
PHP;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
