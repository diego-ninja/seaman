<?php

// ABOUTME: Exports local plugins to distributable Composer packages.
// ABOUTME: Transforms namespaces and generates composer.json files.

declare(strict_types=1);

namespace Seaman\Plugin\Export;

interface PluginExporter
{
    /**
     * Export a local plugin to a Composer package.
     *
     * @param string $pluginPath Absolute path to the plugin directory
     * @param string $outputPath Absolute path to the output directory
     * @param string $vendorName Vendor name for the Composer package
     */
    public function export(string $pluginPath, string $outputPath, string $vendorName): void;
}
