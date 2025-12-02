<?php

declare(strict_types=1);

// ABOUTME: Discovers service implementations using filesystem scanning.
// ABOUTME: Loads and instantiates all ServiceInterface implementations from directory.

namespace Seaman\Service\Container;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RegexIterator;

final readonly class ServiceDiscovery
{
    public function __construct(
        private string $serviceDir,
    ) {}

    /**
     * Discover all service implementations in the service directory.
     *
     * @return list<ServiceInterface>
     */
    public function discover(): array
    {
        if (!is_dir($this->serviceDir)) {
            return [];
        }

        $services = [];

        foreach ($this->scanDirectory() as $filePath) {
            $service = $this->loadService($filePath);
            if ($service !== null) {
                $services[] = $service;
            }
        }

        return $services;
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
                new RecursiveDirectoryIterator($this->serviceDir),
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
     * Load service from PHP file using reflection.
     *
     * @param string $filePath Absolute path to PHP file
     * @return ServiceInterface|null
     */
    private function loadService(string $filePath): ?ServiceInterface
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

            // Skip if doesn't implement ServiceInterface
            if (!$reflection->implementsInterface(ServiceInterface::class)) {
                return null;
            }

            // Skip abstract classes and interfaces
            if (!$reflection->isInstantiable()) {
                return null;
            }

            /** @var ServiceInterface */
            return $reflection->newInstance();
        } catch (\Throwable) {
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

        // Extract class name (handle readonly modifier)
        if (!preg_match('/(?:readonly\s+)?class\s+(\w+)/', $content, $classMatch)) {
            return null;
        }

        return $namespaceMatch[1] . '\\' . $classMatch[1];
    }
}
