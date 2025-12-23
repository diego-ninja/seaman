<?php

// ABOUTME: Manages template overrides from plugins.
// ABOUTME: Provides mapping between core templates and plugin replacements.

declare(strict_types=1);

namespace Seaman\Plugin;

use Seaman\Plugin\Extractor\TemplateExtractor;

final readonly class PluginTemplateLoader
{
    public function __construct(
        private PluginRegistry $registry,
        private TemplateExtractor $extractor,
    ) {}

    /**
     * Get all template overrides from registered plugins.
     *
     * Returns a map of core template paths to their plugin override paths.
     * Later plugins override earlier ones for the same template.
     *
     * @return array<string, string>
     */
    public function getOverrides(): array
    {
        $overrides = [];

        foreach ($this->registry->all() as $loadedPlugin) {
            $pluginOverrides = $this->extractor->extract($loadedPlugin->instance);

            foreach ($pluginOverrides as $override) {
                $overrides[$override->originalTemplate] = $override->overridePath;
            }
        }

        return $overrides;
    }

    /**
     * Get template directories for all plugins.
     *
     * Returns a map of plugin names to their template directory paths.
     * Only includes plugins that have a templates directory.
     *
     * @return array<string, string>
     */
    public function getPluginTemplatePaths(): array
    {
        $paths = [];

        foreach ($this->registry->all() as $name => $loadedPlugin) {
            $reflection = new \ReflectionClass($loadedPlugin->instance);
            $pluginDir = dirname($reflection->getFileName() ?: '');
            $templateDir = $pluginDir . '/templates';

            if (is_dir($templateDir)) {
                $paths[$name] = $templateDir;
            }
        }

        return $paths;
    }
}
