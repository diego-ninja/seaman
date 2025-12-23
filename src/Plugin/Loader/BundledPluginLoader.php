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

        $pattern = $this->pluginsDir . '/*/src/*Plugin.php';
        $files = glob($pattern);

        if ($files === false) {
            // glob() returns false on error (e.g., permission denied)
            return [];
        }

        foreach ($files as $filePath) {
            $plugin = $this->loadPluginFromFile($filePath);
            if ($plugin !== null) {
                $plugins[] = $plugin;
            }
        }

        return $plugins;
    }
}
