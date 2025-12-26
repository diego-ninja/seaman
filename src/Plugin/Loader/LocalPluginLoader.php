<?php

// ABOUTME: Loads plugins from a local directory (.seaman/plugins/).
// ABOUTME: Scans recursively for PHP classes with AsSeamanPlugin attribute.

declare(strict_types=1);

namespace Seaman\Plugin\Loader;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Seaman\Plugin\PluginInterface;

/**
 * Loader for project-local plugins.
 *
 * Scans .seaman/plugins/ recursively for any PHP file
 * that contains a valid Seaman plugin class.
 */
final readonly class LocalPluginLoader implements PluginLoaderInterface
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

        foreach ($this->scanPhpFiles() as $filePath) {
            $plugin = $this->loadPluginFromFile($filePath);
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
}
