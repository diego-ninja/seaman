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
