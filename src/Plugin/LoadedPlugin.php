<?php

// ABOUTME: Represents a fully loaded and configured plugin.
// ABOUTME: Contains plugin instance, validated config, and source information.

declare(strict_types=1);

namespace Seaman\Plugin;

use Seaman\Plugin\Config\PluginConfig;

final readonly class LoadedPlugin
{
    public function __construct(
        public PluginInterface $instance,
        public PluginConfig $config,
        public string $source,
    ) {}
}
