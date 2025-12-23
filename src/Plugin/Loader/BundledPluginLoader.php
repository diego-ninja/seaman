<?php

// ABOUTME: Loads bundled plugins from the plugins/ directory within Seaman.
// ABOUTME: These plugins ship with Seaman and are always available.

declare(strict_types=1);

namespace Seaman\Plugin\Loader;

use ReflectionClass;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\PluginInterface;

final readonly class BundledPluginLoader implements PluginLoaderInterface
{
    public function __construct(
        private string $pluginsDir,
    ) {}

    /**
     * @return list<PluginInterface>
     */
    public function load(): array
    {
        if (!is_dir($this->pluginsDir)) {
            return [];
        }

        $plugins = [];

        // Scan plugins/*/src/*Plugin.php
        $pattern = $this->pluginsDir . '/*/src/*Plugin.php';
        $files = glob($pattern);

        if ($files === false) {
            return [];
        }

        foreach ($files as $filePath) {
            $plugin = $this->loadPlugin($filePath);
            if ($plugin !== null) {
                $plugins[] = $plugin;
            }
        }

        return $plugins;
    }

    private function loadPlugin(string $filePath): ?PluginInterface
    {
        $className = $this->extractClassName($filePath);
        if ($className === null) {
            return null;
        }

        // For bundled plugins, classes should already be autoloaded
        // But require_once for safety during development
        require_once $filePath;

        if (!class_exists($className)) {
            return null;
        }

        try {
            $reflection = new ReflectionClass($className);

            $attributes = $reflection->getAttributes(AsSeamanPlugin::class);
            if (empty($attributes)) {
                return null;
            }

            if (!$reflection->implementsInterface(PluginInterface::class)) {
                return null;
            }

            if (!$reflection->isInstantiable()) {
                return null;
            }

            /** @var PluginInterface */
            return $reflection->newInstance();
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractClassName(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        if (!preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch)) {
            return null;
        }

        if (!preg_match('/(?:final\s+)?(?:readonly\s+)?class\s+(\w+)/', $content, $classMatch)) {
            return null;
        }

        return $namespaceMatch[1] . '\\' . $classMatch[1];
    }
}
