<?php

declare(strict_types=1);

// ABOUTME: Renders Twig templates for Docker configurations.
// ABOUTME: Handles template loading and variable substitution with plugin override support.

namespace Seaman\Service;

use Seaman\Plugin\PluginTemplateLoader;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class TemplateRenderer
{
    private Environment $twig;
    /** @var array<string, string> */
    private array $overrides = [];

    public function __construct(
        string $templateDirectory,
        ?PluginTemplateLoader $pluginLoader = null,
    ) {
        if (!is_dir($templateDirectory)) {
            throw new \RuntimeException("Template directory not found: {$templateDirectory}");
        }

        $loader = new FilesystemLoader($templateDirectory);
        $this->twig = new Environment($loader, [
            'autoescape' => false, // Docker configs shouldn't be HTML-escaped
            'strict_variables' => true,
        ]);

        if ($pluginLoader !== null) {
            $this->overrides = $pluginLoader->getOverrides();
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context): string
    {
        // Check if there's a plugin override for this template
        $templatePath = $this->overrides[$template] ?? null;

        if ($templatePath !== null) {
            // Render the override template directly from absolute path
            return $this->renderAbsolutePath($templatePath, $context);
        }

        // Render core template
        try {
            return $this->twig->render($template, $context);
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Failed to render template '{$template}': " . $e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderAbsolutePath(string $path, array $context): string
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Template override not found: {$path}");
        }

        // Create a temporary loader for this specific file
        $dirname = dirname($path);
        $basename = basename($path);

        $tempLoader = new FilesystemLoader($dirname);
        $tempTwig = new Environment($tempLoader, [
            'autoescape' => false,
            'strict_variables' => true,
        ]);

        try {
            return $tempTwig->render($basename, $context);
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Failed to render template override '{$path}': " . $e->getMessage(),
                previous: $e,
            );
        }
    }
}
