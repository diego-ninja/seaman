<?php

// ABOUTME: Loads plugins from a local directory (.seaman/plugins/).
// ABOUTME: Scans for PHP classes with AsSeamanPlugin attribute.

declare(strict_types=1);

namespace Seaman\Plugin\Loader;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RegexIterator;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\PluginInterface;

final readonly class LocalPluginLoader implements PluginLoaderInterface
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

        foreach ($this->scanPhpFiles() as $filePath) {
            $plugin = $this->loadPlugin($filePath);
            if ($plugin !== null) {
                $plugins[] = $plugin;
            }
        }

        return $plugins;
    }

    /**
     * @return list<string>
     */
    private function scanPhpFiles(): array
    {
        $files = [];

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->pluginsDir),
            );

            $phpFiles = new RegexIterator($iterator, '/\.php$/');

            /** @var \SplFileInfo $file */
            foreach ($phpFiles as $file) {
                if ($file->isFile()) {
                    $files[] = $file->getPathname();
                }
            }
        } catch (\Exception) {
            return [];
        }

        return $files;
    }

    private function loadPlugin(string $filePath): ?PluginInterface
    {
        $className = $this->extractClassName($filePath);
        if ($className === null) {
            return null;
        }

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
