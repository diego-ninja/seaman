<?php

// ABOUTME: Transforms namespaces in PHP files for plugin export.
// ABOUTME: Replaces namespace declarations, use statements, and FQCNs.

declare(strict_types=1);

namespace Seaman\Plugin\Export;

final readonly class NamespaceTransformer
{
    /**
     * Transform namespaces in PHP code.
     *
     * @param string $content PHP file content
     * @param string $fromNamespace Source namespace to replace
     * @param string $toNamespace Target namespace
     * @return string Transformed content
     */
    public function transform(string $content, string $fromNamespace, string $toNamespace): string
    {
        // Escape backslashes for regex
        $fromEscaped = preg_quote($fromNamespace, '/');

        // Transform namespace declaration (exact match or with sub-namespaces)
        // Match: namespace Seaman\LocalPlugins\MyPlugin; or namespace Seaman\LocalPlugins\MyPlugin\Command;
        $content = preg_replace(
            '/^namespace\s+' . $fromEscaped . '((?:\\\\[A-Za-z0-9_]+)*)\s*;/m',
            'namespace ' . $toNamespace . '$1;',
            $content,
        ) ?? $content;

        // Transform use statements
        // Match: use Seaman\LocalPlugins\MyPlugin\...;
        $content = preg_replace(
            '/^use\s+' . $fromEscaped . '(\\\\[^;]+);/m',
            'use ' . $toNamespace . '$1;',
            $content,
        ) ?? $content;

        // Transform fully qualified class names (with leading backslash)
        // Match: \Seaman\LocalPlugins\MyPlugin\...
        $content = preg_replace(
            '/\\\\' . $fromEscaped . '(\\\\[A-Za-z0-9_\\\\]+)/',
            '\\' . $toNamespace . '$1',
            $content,
        ) ?? $content;

        return $content;
    }
}
