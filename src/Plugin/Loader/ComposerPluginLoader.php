<?php

// ABOUTME: Loads plugins from Composer packages.
// ABOUTME: Scans vendor/ for packages with type "seaman-plugin".

declare(strict_types=1);

namespace Seaman\Plugin\Loader;

use ReflectionClass;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\PluginInterface;

final readonly class ComposerPluginLoader implements PluginLoaderInterface
{
    public function __construct(
        private string $projectRoot,
    ) {}

    /**
     * @return list<PluginInterface>
     */
    public function load(): array
    {
        $installedFile = $this->projectRoot . '/vendor/composer/installed.json';

        if (!file_exists($installedFile)) {
            return [];
        }

        $content = file_get_contents($installedFile);
        if ($content === false) {
            return [];
        }

        $installed = json_decode($content, true);
        if (!is_array($installed)) {
            return [];
        }

        $packages = $installed['packages'] ?? $installed;
        if (!is_array($packages)) {
            return [];
        }

        $plugins = [];

        foreach ($packages as $package) {
            if (!is_array($package)) {
                continue;
            }
            /** @var array{name?: string, type?: string, extra?: array{seaman?: array{plugin-class?: string}}} $package */

            $type = $package['type'] ?? '';
            if ($type !== 'seaman-plugin') {
                continue;
            }

            $pluginClass = $package['extra']['seaman']['plugin-class'] ?? null;
            if ($pluginClass === null) {
                continue;
            }

            $plugin = $this->loadPluginClass($pluginClass);
            if ($plugin !== null) {
                $plugins[] = $plugin;
            }
        }

        return $plugins;
    }

    private function loadPluginClass(string $className): ?PluginInterface
    {
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
}
