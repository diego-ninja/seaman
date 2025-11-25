<?php

declare(strict_types=1);

// ABOUTME: Renders Twig templates for Docker configurations.
// ABOUTME: Handles template loading and variable substitution.

namespace Seaman\Service;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class TemplateRenderer
{
    private Environment $twig;

    public function __construct(string $templateDirectory)
    {
        if (!is_dir($templateDirectory)) {
            throw new \RuntimeException("Template directory not found: {$templateDirectory}");
        }

        $loader = new FilesystemLoader($templateDirectory);
        $this->twig = new Environment($loader, [
            'autoescape' => false, // Docker configs shouldn't be HTML-escaped
            'strict_variables' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context): string
    {
        try {
            return $this->twig->render($template, $context);
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Failed to render template '{$template}': " . $e->getMessage(),
                previous: $e,
            );
        }
    }
}
