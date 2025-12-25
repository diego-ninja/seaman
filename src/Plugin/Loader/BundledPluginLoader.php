<?php

// ABOUTME: Loads bundled plugins from the plugins/ directory within Seaman.
// ABOUTME: These plugins ship with Seaman and are always available.

declare(strict_types=1);

namespace Seaman\Plugin\Loader;

use Seaman\Plugin\PluginInterface;

/**
 * Loader for plugins bundled with Seaman.
 *
 * Bundled plugins must follow this structure:
 * - plugins/<plugin-name>/src/<PluginName>Plugin.php
 *
 * Example: plugins/redis/src/RedisPlugin.php
 */
final readonly class BundledPluginLoader implements PluginLoaderInterface
{
    use PluginLoaderTrait;

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

        // Use scandir instead of glob() because glob() doesn't work inside PHAR
        $pluginDirs = scandir($this->pluginsDir);
        if ($pluginDirs === false) {
            return [];
        }

        foreach ($pluginDirs as $pluginDir) {
            if ($pluginDir === '.' || $pluginDir === '..') {
                continue;
            }

            $srcDir = $this->pluginsDir . '/' . $pluginDir . '/src';
            if (!is_dir($srcDir)) {
                continue;
            }

            $srcFiles = scandir($srcDir);
            if ($srcFiles === false) {
                continue;
            }

            foreach ($srcFiles as $file) {
                if (str_ends_with($file, 'Plugin.php')) {
                    $filePath = $srcDir . '/' . $file;
                    $plugin = $this->loadPluginFromFile($filePath);
                    if ($plugin !== null) {
                        $plugins[] = $plugin;
                    }
                }
            }
        }

        return $plugins;
    }
}
