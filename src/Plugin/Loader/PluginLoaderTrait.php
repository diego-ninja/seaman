<?php

// ABOUTME: Shared functionality for plugin loaders.
// ABOUTME: Provides class extraction and plugin instantiation logic.

declare(strict_types=1);

namespace Seaman\Plugin\Loader;

use ReflectionClass;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\PluginInterface;

trait PluginLoaderTrait
{
    /**
     * Load a plugin from a PHP file.
     *
     * Returns null if:
     * - File cannot be read or parsed
     * - Class doesn't have #[AsSeamanPlugin] attribute
     * - Class doesn't implement PluginInterface
     * - Class is not instantiable (abstract, interface, trait)
     * - Constructor throws an exception
     */
    private function loadPluginFromFile(string $filePath): ?PluginInterface
    {
        $className = $this->extractClassName($filePath);
        if ($className === null) {
            return null;
        }

        require_once $filePath;

        if (!class_exists($className)) {
            return null;
        }

        try {
            $reflection = new ReflectionClass($className);

            $attributes = $reflection->getAttributes(AsSeamanPlugin::class);
            if ($attributes === []) {
                return null;
            }

            if (!$reflection->implementsInterface(PluginInterface::class)) {
                return null;
            }

            if (!$reflection->isInstantiable()) {
                return null;
            }

            /** @var PluginInterface */
            return $reflection->newInstance();
        } catch (\ReflectionException|\Error) {
            // ReflectionException: class issues, Error: autoload/parse errors
            return null;
        }
    }

    /**
     * Extract fully qualified class name from a PHP file.
     *
     * Parses the file content to find namespace and class declarations.
     * Handles PHP 8.x class modifiers: final, readonly, abstract (in any order).
     */
    private function extractClassName(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        if (!preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch)) {
            return null;
        }

        // Match class declaration with any combination of modifiers
        // Handles: final, readonly, abstract in any order
        // Examples: "final class", "readonly class", "final readonly class",
        //           "readonly final class", "abstract class"
        $classPattern = '/(?:(?:final|readonly|abstract)\s+)*class\s+(\w+)/';
        if (!preg_match($classPattern, $content, $classMatch)) {
            return null;
        }

        return $namespaceMatch[1] . '\\' . $classMatch[1];
    }
}
