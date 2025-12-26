<?php

// ABOUTME: Exception thrown for plugin-related errors.
// ABOUTME: Covers missing src directories, invalid attributes, etc.

declare(strict_types=1);

namespace Seaman\Exception;

final class PluginException extends SeamanException
{
    private string $pluginPath = '';

    public static function missingSrcDirectory(string $pluginPath): self
    {
        $exception = new self("Plugin must have a src directory: {$pluginPath}");
        $exception->pluginPath = $pluginPath;

        return $exception;
    }

    public static function missingPluginAttribute(string $pluginPath): self
    {
        $exception = new self("Could not find #[AsSeamanPlugin] attribute in any PHP file: {$pluginPath}");
        $exception->pluginPath = $pluginPath;

        return $exception;
    }

    public function getPluginPath(): string
    {
        return $this->pluginPath;
    }
}
