<?php

// ABOUTME: Autoloader for plugin classes from vendored dependencies.
// ABOUTME: Maps plugin namespaces to their file system paths.

declare(strict_types=1);

namespace Seaman\Plugin\Loader;

final class PluginAutoloader
{
    /**
     * @var array<string, string>
     * @phpstan-ignore property.onlyWritten
     */
    private array $prefixPaths = [];

    /** @phpstan-ignore property.onlyWritten */
    private bool $registered = false;

    public function loadClass(string $class): bool
    {
        return false;
    }
}
