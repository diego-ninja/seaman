<?php

declare(strict_types=1);

// ABOUTME: Tests for TemplateRenderer service.
// ABOUTME: Validates Twig template rendering.

namespace Seaman\Tests\Unit\Service;

use Seaman\Service\TemplateRenderer;

/**
 * @property TemplateRenderer $renderer
 */
beforeEach(function () {
    $templateDir = __DIR__ . '/../../../src/Template';
    $this->renderer = new TemplateRenderer($templateDir);
});

test('renders simple template', function () {
    // Create a temporary template for testing
    $templateDir = __DIR__ . '/../../../src/Template';
    $testTemplate = $templateDir . '/test.twig';
    file_put_contents($testTemplate, 'Hello {{ name }}!');

    /** @var TemplateRenderer $renderer */
    $renderer = $this->renderer;
    $result = $renderer->render('test.twig', ['name' => 'World']);

    expect($result)->toBe('Hello World!');

    // Cleanup
    unlink($testTemplate);
});

test('renders template with arrays', function () {
    $templateDir = __DIR__ . '/../../../src/Template';
    $testTemplate = $templateDir . '/list.twig';
    file_put_contents($testTemplate, '{% for item in items %}{{ item }}{% if not loop.last %}, {% endif %}{% endfor %}');

    /** @var TemplateRenderer $renderer */
    $renderer = $this->renderer;
    $result = $renderer->render('list.twig', ['items' => ['a', 'b', 'c']]);

    expect($result)->toBe('a, b, c');

    unlink($testTemplate);
});

test('throws when template not found', function () {
    /** @var TemplateRenderer $renderer */
    $renderer = $this->renderer;
    $renderer->render('nonexistent.twig', []);
})->throws(\RuntimeException::class);

test('renders plugin override when configured', function (): void {
    $coreTemplateDir = __DIR__ . '/../../../src/Template';
    $pluginTemplateDir = sys_get_temp_dir() . '/plugin-templates-' . uniqid();
    mkdir($pluginTemplateDir, 0755, true);

    // Create core template
    $coreTemplate = $coreTemplateDir . '/core-template.twig';
    file_put_contents($coreTemplate, 'Core: {{ value }}');

    // Create plugin override
    $pluginTemplate = $pluginTemplateDir . '/override.twig';
    file_put_contents($pluginTemplate, 'Plugin: {{ value }}');

    // Create plugin with override using the attribute
    $pluginTemplatePath = $pluginTemplate;
    $plugin = new class($pluginTemplatePath) implements \Seaman\Plugin\PluginInterface {
        public function __construct(private readonly string $templatePath) {}

        public function getName(): string
        {
            return 'override-plugin';
        }

        public function getVersion(): string
        {
            return '1.0.0';
        }

        public function getDescription(): string
        {
            return 'Override test';
        }

        #[\Seaman\Plugin\Attribute\OverridesTemplate(template: 'core-template.twig')]
        public function customCoreTemplate(): string
        {
            return $this->templatePath;
        }
    };

    $registry = new \Seaman\Plugin\PluginRegistry();
    $registry->register($plugin, []);

    $extractor = new \Seaman\Plugin\Extractor\TemplateExtractor();
    $pluginLoader = new \Seaman\Plugin\PluginTemplateLoader($registry, $extractor);

    $renderer = new TemplateRenderer($coreTemplateDir, $pluginLoader);
    $result = $renderer->render('core-template.twig', ['value' => 'test']);

    expect($result)->toBe('Plugin: test');

    // Cleanup
    unlink($coreTemplate);
    unlink($pluginTemplate);
    rmdir($pluginTemplateDir);
});

test('renders core template when no override exists', function (): void {
    $coreTemplateDir = __DIR__ . '/../../../src/Template';

    // Create core template
    $coreTemplate = $coreTemplateDir . '/no-override.twig';
    file_put_contents($coreTemplate, 'Core: {{ value }}');

    $registry = new \Seaman\Plugin\PluginRegistry();
    $extractor = new \Seaman\Plugin\Extractor\TemplateExtractor();
    $pluginLoader = new \Seaman\Plugin\PluginTemplateLoader($registry, $extractor);

    $renderer = new TemplateRenderer($coreTemplateDir, $pluginLoader);
    $result = $renderer->render('no-override.twig', ['value' => 'test']);

    expect($result)->toBe('Core: test');

    // Cleanup
    unlink($coreTemplate);
});

test('throws when plugin override template not found', function (): void {
    $coreTemplateDir = __DIR__ . '/../../../src/Template';

    $plugin = new class implements \Seaman\Plugin\PluginInterface {
        public function getName(): string
        {
            return 'missing-override-plugin';
        }

        public function getVersion(): string
        {
            return '1.0.0';
        }

        public function getDescription(): string
        {
            return 'Missing override test';
        }

        #[\Seaman\Plugin\Attribute\OverridesTemplate(template: 'some-template.twig')]
        public function missingTemplate(): string
        {
            return '/nonexistent/path/template.twig';
        }
    };

    $registry = new \Seaman\Plugin\PluginRegistry();
    $registry->register($plugin, []);

    $extractor = new \Seaman\Plugin\Extractor\TemplateExtractor();
    $pluginLoader = new \Seaman\Plugin\PluginTemplateLoader($registry, $extractor);
    $renderer = new TemplateRenderer($coreTemplateDir, $pluginLoader);

    $renderer->render('some-template.twig', []);
})->throws(\RuntimeException::class, 'Template override not found');
