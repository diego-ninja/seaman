<?php

// ABOUTME: Contract for plugin loaders.
// ABOUTME: Implementations discover and instantiate plugins from different sources.

declare(strict_types=1);

namespace Seaman\Plugin\Loader;

use Seaman\Plugin\PluginInterface;

interface PluginLoaderInterface
{
    /**
     * Load plugins from the source.
     *
     * @return list<PluginInterface>
     */
    public function load(): array;
}
