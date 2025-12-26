<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin;

use Seaman\Plugin\Extractor\TemplateExtractor;
use Seaman\Plugin\LoadedPlugin;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\PluginRegistry;
use Seaman\Plugin\PluginTemplateLoader;
use Seaman\Plugin\Config\PluginConfig;

beforeEach(function (): void {
    $this->registry = new PluginRegistry();
    $this->extractor = new TemplateExtractor();
    $this->loader = new PluginTemplateLoader($this->registry, $this->extractor);
});

test('getOverrides returns empty array when no plugins registered', function (): void {
    /** @var PluginTemplateLoader $loader */
    $loader = $this->loader;
    $overrides = $loader->getOverrides();

    expect($overrides)->toBe([]);
});

test('getOverrides returns template overrides from registered plugins', function (): void {
    $plugin = new class implements PluginInterface {
        public function getName(): string
        {
            return 'test-plugin';
        }

        public function getVersion(): string
        {
            return '1.0.0';
        }

        public function getDescription(): string
        {
            return 'Test plugin';
        }

        #[\Seaman\Plugin\Attribute\OverridesTemplate(template: 'docker/compose.base.twig')]
        public function customCompose(): string
        {
            return __DIR__ . '/custom-compose.twig';
        }
    };

    $this->registry->register($plugin, []);

    $overrides = $this->loader->getOverrides();

    expect($overrides)->toHaveCount(1);
    expect($overrides['docker/compose.base.twig'])->toContain('custom-compose.twig');
});

test('getOverrides merges overrides from multiple plugins', function (): void {
    $plugin1 = new class implements PluginInterface {
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

        #[\Seaman\Plugin\Attribute\OverridesTemplate(template: 'template-a.twig')]
        public function customTemplateA(): string
        {
            return '/path/to/plugin1-template-a.twig';
        }
    };

    $plugin2 = new class implements PluginInterface {
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

        #[\Seaman\Plugin\Attribute\OverridesTemplate(template: 'template-b.twig')]
        public function customTemplateB(): string
        {
            return '/path/to/plugin2-template-b.twig';
        }
    };

    $this->registry->register($plugin1, []);
    $this->registry->register($plugin2, []);

    $overrides = $this->loader->getOverrides();

    expect($overrides)->toHaveCount(2);
    expect($overrides['template-a.twig'])->toBe('/path/to/plugin1-template-a.twig');
    expect($overrides['template-b.twig'])->toBe('/path/to/plugin2-template-b.twig');
});

test('getOverrides last plugin wins for same template', function (): void {
    $plugin1 = new class implements PluginInterface {
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

        #[\Seaman\Plugin\Attribute\OverridesTemplate(template: 'shared.twig')]
        public function sharedTemplate(): string
        {
            return '/path/to/plugin1-shared.twig';
        }
    };

    $plugin2 = new class implements PluginInterface {
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

        #[\Seaman\Plugin\Attribute\OverridesTemplate(template: 'shared.twig')]
        public function sharedTemplate(): string
        {
            return '/path/to/plugin2-shared.twig';
        }
    };

    $this->registry->register($plugin1, []);
    $this->registry->register($plugin2, []);

    $overrides = $this->loader->getOverrides();

    expect($overrides)->toHaveCount(1);
    expect($overrides['shared.twig'])->toBe('/path/to/plugin2-shared.twig');
});

test('getPluginTemplatePaths returns empty array when no plugins registered', function (): void {
    $paths = $this->loader->getPluginTemplatePaths();

    expect($paths)->toBe([]);
});

test('getPluginTemplatePaths returns paths for plugins with templates directory', function (): void {
    // Skip this test in PHPStan since it dynamically creates classes
    if (defined('__PHPSTAN_RUNNING__')) {
        expect(true)->toBeTrue();
        return;
    }

    // Create temp plugin directory with templates
    $tempDir = sys_get_temp_dir() . '/seaman-plugin-test-' . uniqid();
    $templatesDir = $tempDir . '/templates';
    mkdir($templatesDir, 0755, true);

    // Create a temporary plugin class file
    $pluginCode = <<<'PHP'
<?php
namespace TempPlugin;
class TestPlugin implements \Seaman\Plugin\PluginInterface {
    public function getName(): string { return 'test-plugin'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getDescription(): string { return 'Test'; }
}
PHP;

    $pluginFile = $tempDir . '/TestPlugin.php';
    file_put_contents($pluginFile, $pluginCode);

    require_once $pluginFile;

    /** @var \Seaman\Plugin\PluginInterface $plugin */
    $plugin = new \TempPlugin\TestPlugin();
    $this->registry->register($plugin, []);

    $paths = $this->loader->getPluginTemplatePaths();

    expect($paths)->toHaveKey('test-plugin');
    $pluginPath = $paths['test-plugin'] ?? '';
    expect(realpath($pluginPath))->toBe(realpath($templatesDir));

    // Cleanup
    unlink($pluginFile);
    rmdir($templatesDir);
    rmdir($tempDir);
});

test('getPluginTemplatePaths excludes plugins without templates directory', function (): void {
    $plugin = new class implements PluginInterface {
        public function getName(): string
        {
            return 'no-templates-plugin';
        }

        public function getVersion(): string
        {
            return '1.0.0';
        }

        public function getDescription(): string
        {
            return 'Plugin without templates';
        }
    };

    $this->registry->register($plugin, []);

    $paths = $this->loader->getPluginTemplatePaths();

    expect($paths)->toBe([]);
});
