<?php

// ABOUTME: Exports local plugins to distributable Composer packages.
// ABOUTME: Transforms namespaces and generates composer.json files.

declare(strict_types=1);

namespace Seaman\Plugin\Export;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final readonly class PluginExporter
{
    public function __construct(
        private NamespaceTransformer $namespaceTransformer,
    ) {}

    public function export(string $pluginPath, string $outputPath, string $vendorName): void
    {
        // Validate plugin structure
        $srcPath = $pluginPath . '/src';
        if (!is_dir($srcPath)) {
            throw new RuntimeException('Plugin must have a src directory');
        }

        // Extract plugin metadata
        $metadata = $this->extractPluginMetadata($srcPath);

        // Create output directory
        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0755, true);
        }

        // Copy and transform src directory
        $this->copySrcDirectory($srcPath, $outputPath . '/src', $vendorName, $metadata);

        // Copy templates directory if exists
        $templatesPath = $pluginPath . '/templates';
        if (is_dir($templatesPath)) {
            $this->copyDirectory($templatesPath, $outputPath . '/templates');
        }

        // Generate composer.json
        $this->generateComposerJson($outputPath, $vendorName, $metadata);
    }

    /**
     * @return array{name: string, version: string, description: string, namespace: string, className: string}
     */
    private function extractPluginMetadata(string $srcPath): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcPath, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            // Check if file has AsSeamanPlugin attribute
            if (!str_contains($content, '#[AsSeamanPlugin')) {
                continue;
            }

            // Extract namespace from file
            if (!preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
                continue;
            }
            $namespace = $matches[1];

            // Extract class name from file
            if (!preg_match('/class\s+(\w+)/', $content, $classMatches)) {
                continue;
            }
            $className = $classMatches[1];

            // Extract attribute parameters using regex
            // Match: #[AsSeamanPlugin(name: 'plugin-name', version: '1.0.0', description: 'desc')]
            $pattern = '/#\[AsSeamanPlugin\s*\((.*?)\)\s*\]/s';
            if (!preg_match($pattern, $content, $attrMatches)) {
                continue;
            }

            $attributeContent = $attrMatches[1];

            // Extract name
            if (!preg_match('/name:\s*[\'"]([^\'"]+)[\'"]/', $attributeContent, $nameMatch)) {
                continue;
            }
            $name = $nameMatch[1];

            // Extract version (optional, default to '1.0.0')
            $version = '1.0.0';
            if (preg_match('/version:\s*[\'"]([^\'"]+)[\'"]/', $attributeContent, $versionMatch)) {
                $version = $versionMatch[1];
            }

            // Extract description (optional, default to empty string)
            $description = '';
            if (preg_match('/description:\s*[\'"]([^\'"]+)[\'"]/', $attributeContent, $descMatch)) {
                $description = $descMatch[1];
            }

            return [
                'name' => $name,
                'version' => $version,
                'description' => $description,
                'namespace' => $namespace,
                'className' => $className,
            ];
        }

        throw new RuntimeException('Could not find AsSeamanPlugin attribute in any PHP file');
    }

    /**
     * @param array{name: string, version: string, description: string, namespace: string, className: string} $metadata
     */
    private function copySrcDirectory(string $srcPath, string $outputPath, string $vendorName, array $metadata): void
    {
        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0755, true);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            $targetPath = $outputPath . '/' . $iterator->getSubPathname();

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
                continue;
            }

            if ($item->getExtension() === 'php') {
                // Transform namespace in PHP files
                $content = file_get_contents($item->getPathname());
                if ($content !== false) {
                    $targetNamespace = $this->buildTargetNamespace($vendorName, $metadata['namespace']);
                    $transformedContent = $this->namespaceTransformer->transform(
                        $content,
                        $metadata['namespace'],
                        $targetNamespace,
                    );
                    file_put_contents($targetPath, $transformedContent);
                }
            } else {
                // Copy non-PHP files as-is
                copy($item->getPathname(), $targetPath);
            }
        }
    }

    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            $targetPath = $destination . '/' . $iterator->getSubPathname();

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                copy($item->getPathname(), $targetPath);
            }
        }
    }

    /**
     * @param array{name: string, version: string, description: string, namespace: string, className: string} $metadata
     */
    private function generateComposerJson(string $outputPath, string $vendorName, array $metadata): void
    {
        $targetNamespace = $this->buildTargetNamespace($vendorName, $metadata['namespace']);
        $packageName = $vendorName . '/' . $metadata['name'];

        $composer = [
            'name' => $packageName,
            'description' => $metadata['description'],
            'type' => 'seaman-plugin',
            'license' => 'MIT',
            'require' => [
                'php' => '^8.4',
            ],
            'require-dev' => [
                'seaman/seaman' => '^1.0',
            ],
            'autoload' => [
                'psr-4' => [
                    $targetNamespace . '\\' => 'src/',
                ],
            ],
            'extra' => [
                'seaman' => [
                    'plugin-class' => $targetNamespace . '\\' . $metadata['className'],
                ],
            ],
        ];

        $json = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($outputPath . '/composer.json', $json . "\n");
    }

    private function buildTargetNamespace(string $vendorName, string $sourceNamespace): string
    {
        // Extract the plugin name from the source namespace
        // Seaman\LocalPlugins\MyPlugin -> MyPlugin
        $parts = explode('\\', $sourceNamespace);
        $pluginNameFromNamespace = end($parts);

        // Convert vendor name to PascalCase
        // diego -> Diego, my-vendor -> MyVendor
        $vendorParts = explode('-', strtolower($vendorName));
        $vendorNamePascal = implode('', array_map('ucfirst', $vendorParts));

        return $vendorNamePascal . '\\' . $pluginNameFromNamespace;
    }
}
