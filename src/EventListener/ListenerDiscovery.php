<?php

// ABOUTME: Discovers event listeners using reflection and attributes.
// ABOUTME: Scans listener directory for classes with AsEventListener attribute.

declare(strict_types=1);

namespace Seaman\EventListener;

use Seaman\Attribute\AsEventListener;
use ReflectionClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

final readonly class ListenerDiscovery
{
    public function __construct(
        private string $listenerDir,
    ) {}

    /**
     * Discover all event listeners in the listener directory.
     *
     * @return list<EventListenerMetadata>
     */
    public function discover(): array
    {
        if (!is_dir($this->listenerDir)) {
            return [];
        }

        $listeners = [];

        foreach ($this->scanDirectory() as $filePath) {
            $metadata = $this->extractMetadata($filePath);
            if ($metadata !== null) {
                $listeners[] = $metadata;
            }
        }

        // Sort by priority (descending - higher priority first)
        usort($listeners, fn($a, $b) => $b->priority <=> $a->priority);

        return $listeners;
    }

    /**
     * Scan directory for PHP files.
     *
     * @return list<string> File paths
     */
    private function scanDirectory(): array
    {
        $files = [];

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->listenerDir),
            );

            $phpFiles = new RegexIterator($iterator, '/\.php$/');

            foreach ($phpFiles as $file) {
                if ($file->isFile()) {
                    $pathName = $file->getPathname();
                    if (is_string($pathName)) {
                        $files[] = $pathName;
                    }
                }
            }
        } catch (\Exception) {
            return [];
        }

        return $files;
    }

    /**
     * Extract listener metadata from PHP file using reflection.
     *
     * @param string $filePath Absolute path to PHP file
     * @return EventListenerMetadata|null
     */
    private function extractMetadata(string $filePath): ?EventListenerMetadata
    {
        $className = $this->getClassNameFromFile($filePath);
        if ($className === null) {
            return null;
        }

        // Require the file to load the class
        require_once $filePath;

        try {
            if (!class_exists($className)) {
                return null;
            }

            $reflection = new ReflectionClass($className);
            $attributes = $reflection->getAttributes(AsEventListener::class);

            if (empty($attributes)) {
                return null;
            }

            $attribute = $attributes[0]->newInstance();

            return new EventListenerMetadata(
                className: $className,
                event: $attribute->event,
                priority: $attribute->priority,
            );
        } catch (\ReflectionException) {
            return null;
        }
    }

    /**
     * Extract fully qualified class name from PHP file.
     *
     * @param string $filePath Absolute path to PHP file
     * @return string|null Fully qualified class name or null
     */
    private function getClassNameFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        // Extract namespace
        if (!preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch)) {
            return null;
        }

        // Extract class name
        if (!preg_match('/class\s+(\w+)/', $content, $classMatch)) {
            return null;
        }

        return $namespaceMatch[1] . '\\' . $classMatch[1];
    }
}
