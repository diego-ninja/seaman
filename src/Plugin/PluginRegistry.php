<?php

// ABOUTME: Central registry for loaded plugins.
// ABOUTME: Manages plugin lifecycle and provides access to plugin instances.

declare(strict_types=1);

namespace Seaman\Plugin;

use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\Config\PluginConfig;

final class PluginRegistry
{
    /** @var array<string, LoadedPlugin> */
    private array $plugins = [];

    /**
     * @param array<string, mixed> $config
     */
    public function register(PluginInterface $plugin, array $config, string $source = 'unknown'): void
    {
        $name = $plugin->getName();

        // Validate config if plugin defines a schema
        $validatedConfig = $this->validateConfig($plugin, $config);

        $this->plugins[$name] = new LoadedPlugin(
            instance: $plugin,
            config: new PluginConfig($validatedConfig),
            source: $source,
        );
    }

    public function has(string $name): bool
    {
        return isset($this->plugins[$name]);
    }

    public function get(string $name): LoadedPlugin
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException("Plugin '{$name}' not found");
        }

        return $this->plugins[$name];
    }

    /**
     * @return array<string, LoadedPlugin>
     */
    public function all(): array
    {
        return $this->plugins;
    }

    /**
     * @param array<string, array<string, mixed>> $pluginConfig
     */
    public static function discover(
        string $projectRoot,
        string $localPluginsDir,
        array $pluginConfig,
        ?string $bundledPluginsDir = null,
    ): self {
        $registry = new self();

        // 1. Load bundled plugins first (lowest priority, can be overridden)
        if ($bundledPluginsDir !== null && is_dir($bundledPluginsDir)) {
            $bundledLoader = new Loader\BundledPluginLoader($bundledPluginsDir);
            foreach ($bundledLoader->load() as $plugin) {
                $config = $pluginConfig[$plugin->getName()] ?? [];
                $registry->register($plugin, $config, 'bundled');
            }
        }

        // 2. Load Composer plugins (can override bundled)
        $composerLoader = new Loader\ComposerPluginLoader($projectRoot);
        foreach ($composerLoader->load() as $plugin) {
            $config = $pluginConfig[$plugin->getName()] ?? [];
            $registry->register($plugin, $config, 'composer');
        }

        // 3. Load local plugins (highest priority, can override all)
        $localLoader = new Loader\LocalPluginLoader($localPluginsDir);
        foreach ($localLoader->load() as $plugin) {
            $config = $pluginConfig[$plugin->getName()] ?? [];
            $registry->register($plugin, $config, 'local');
        }

        return $registry;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function validateConfig(PluginInterface $plugin, array $config): array
    {
        if (!method_exists($plugin, 'configSchema')) {
            return $config;
        }

        /** @var ConfigSchema $schema */
        $schema = $plugin->configSchema();

        return $schema->validate($config);
    }
}
