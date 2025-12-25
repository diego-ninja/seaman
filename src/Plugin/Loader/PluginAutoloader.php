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

    /**
     * Resolves plugin packages and all their transitive dependencies.
     *
     * @param list<string> $pluginNames
     * @param list<array{name: string, require?: array<string, string>, autoload?: array{psr-4?: array<string, string|list<string>>}, install-path?: string}> $installedPackages
     * @return list<array{name: string, require?: array<string, string>, autoload?: array{psr-4?: array<string, string|list<string>>}, install-path?: string}>
     */
    public function resolveWithDependencies(
        array $pluginNames,
        array $installedPackages,
    ): array {
        /** @var array<string, array{name: string, require?: array<string, string>, autoload?: array{psr-4?: array<string, string|list<string>>}, install-path?: string}> $packagesByName */
        $packagesByName = [];
        foreach ($installedPackages as $package) {
            $packagesByName[$package['name']] = $package;
        }

        /** @var list<array{name: string, require?: array<string, string>, autoload?: array{psr-4?: array<string, string|list<string>>}, install-path?: string}> $resolved */
        $resolved = [];
        $queue = $pluginNames;
        /** @var array<string, true> $seen */
        $seen = [];

        while (!empty($queue)) {
            $name = array_shift($queue);

            if (isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;

            if (!isset($packagesByName[$name])) {
                continue;
            }

            $package = $packagesByName[$name];
            $resolved[] = $package;

            $requires = $package['require'] ?? [];
            foreach (array_keys($requires) as $dep) {
                /** @var string $dep */
                if (!$this->isPlatformDependency($dep)) {
                    $queue[] = $dep;
                }
            }
        }

        return $resolved;
    }

    /**
     * Registers the autoloader for plugin packages and their dependencies.
     *
     * @param list<string> $pluginPackageNames
     * @param list<array{name: string, require?: array<string, string>, autoload?: array{psr-4?: array<string, string|list<string>>}, install-path?: string}> $installedPackages
     */
    public function register(
        string $projectRoot,
        array $pluginPackageNames,
        array $installedPackages,
    ): void {
        if ($this->registered) {
            return;
        }

        $relevantPackages = $this->resolveWithDependencies(
            $pluginPackageNames,
            $installedPackages,
        );

        $vendorDir = $projectRoot . '/vendor/composer';

        foreach ($relevantPackages as $package) {
            $this->addPackageMappings($package, $vendorDir);
        }

        if (!empty($this->prefixPaths)) {
            spl_autoload_register(function (string $class): void {
                $this->loadClass($class);
            });
            $this->registered = true;
        }
    }

    private function isPlatformDependency(string $name): bool
    {
        return str_starts_with($name, 'php')
            || str_starts_with($name, 'ext-')
            || str_starts_with($name, 'lib-')
            || $name === 'composer-plugin-api';
    }
}
