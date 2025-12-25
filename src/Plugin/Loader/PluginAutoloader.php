<?php

// ABOUTME: Autoloader for plugin classes from vendored dependencies.
// ABOUTME: Maps plugin namespaces to their file system paths.

declare(strict_types=1);

namespace Seaman\Plugin\Loader;

final class PluginAutoloader
{
    /**
     * @var array<string, list<string>>
     */
    private array $prefixPaths = [];

    /** @phpstan-ignore property.onlyWritten */
    private bool $registered = false;

    /**
     * @param array{install-path?: string, autoload?: array{psr-4?: array<string, string|list<string>>}} $package
     */
    public function addPackageMappings(array $package, string $vendorDir): void
    {
        $psr4 = $package['autoload']['psr-4'] ?? [];
        $installPath = $package['install-path'] ?? '';

        if (empty($psr4) || $installPath === '') {
            return;
        }

        $basePath = $vendorDir !== ''
            ? rtrim($vendorDir, '/') . '/' . $installPath . '/'
            : $installPath . '/';

        foreach ($psr4 as $prefix => $path) {
            $paths = is_array($path) ? $path : [$path];

            foreach ($paths as $p) {
                $fullPath = $basePath . rtrim($p, '/') . '/';
                $this->prefixPaths[$prefix][] = $fullPath;
            }
        }
    }

    public function loadClass(string $class): bool
    {
        foreach ($this->prefixPaths as $prefix => $paths) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            $relativeClass = substr($class, strlen($prefix));
            $file = str_replace('\\', '/', $relativeClass) . '.php';

            foreach ($paths as $basePath) {
                $fullPath = $basePath . $file;
                if (file_exists($fullPath)) {
                    require $fullPath;
                    return true;
                }
            }
        }

        return false;
    }
}
