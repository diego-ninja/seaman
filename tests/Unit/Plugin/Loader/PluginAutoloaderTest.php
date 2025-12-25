<?php

declare(strict_types=1);

namespace Tests\Unit\Plugin\Loader;

use PHPUnit\Framework\TestCase;
use Seaman\Plugin\Loader\PluginAutoloader;

final class PluginAutoloaderTest extends TestCase
{
    public function testLoadClassReturnsFalseWhenNoMappingsRegistered(): void
    {
        $autoloader = new PluginAutoloader();

        $result = $autoloader->loadClass('NonExistent\\SomeClass');

        self::assertFalse($result);
    }

    public function testLoadClassResolvesClassFromAddedMappings(): void
    {
        $autoloader = new PluginAutoloader();

        // Create temp directory with a test class
        $tempDir = sys_get_temp_dir() . '/plugin-autoloader-test-' . uniqid();
        mkdir($tempDir . '/src', 0777, true);

        $className = 'TestVendor' . uniqid() . '\\TestPlugin\\TestPlugin';
        $namespace = substr($className, 0, strrpos($className, '\\') + 1);
        $shortClass = substr($className, strrpos($className, '\\') + 1);

        file_put_contents(
            $tempDir . '/src/' . $shortClass . '.php',
            "<?php namespace " . rtrim($namespace, '\\') . "; class {$shortClass} {}",
        );

        $package = [
            'name' => 'test-vendor/test-plugin',
            'install-path' => $tempDir,
            'autoload' => [
                'psr-4' => [
                    $namespace => 'src/',
                ],
            ],
        ];

        $autoloader->addPackageMappings($package, '');

        $result = $autoloader->loadClass($className);

        self::assertTrue($result);
        self::assertTrue(class_exists($className, false));

        // Cleanup
        unlink($tempDir . '/src/' . $shortClass . '.php');
        rmdir($tempDir . '/src');
        rmdir($tempDir);
    }

    public function testResolveWithDependenciesIncludesTransitiveDependencies(): void
    {
        $autoloader = new PluginAutoloader();

        $packages = [
            [
                'name' => 'acme/seaman-redis',
                'require' => ['predis/predis' => '^2.0'],
                'autoload' => ['psr-4' => ['Acme\\Redis\\' => 'src/']],
                'install-path' => '../acme/seaman-redis',
            ],
            [
                'name' => 'predis/predis',
                'require' => ['php' => '>=8.1'],
                'autoload' => ['psr-4' => ['Predis\\' => 'src/']],
                'install-path' => '../predis/predis',
            ],
            [
                'name' => 'unrelated/package',
                'autoload' => ['psr-4' => ['Unrelated\\' => 'src/']],
                'install-path' => '../unrelated/package',
            ],
        ];

        $resolved = $autoloader->resolveWithDependencies(
            ['acme/seaman-redis'],
            $packages,
        );

        $resolvedNames = array_column($resolved, 'name');

        self::assertContains('acme/seaman-redis', $resolvedNames);
        self::assertContains('predis/predis', $resolvedNames);
        self::assertNotContains('unrelated/package', $resolvedNames);
    }

    public function testResolveWithDependenciesIgnoresPlatformDependencies(): void
    {
        $autoloader = new PluginAutoloader();

        $packages = [
            [
                'name' => 'acme/seaman-redis',
                'require' => [
                    'php' => '>=8.1',
                    'ext-json' => '*',
                    'lib-pcre' => '*',
                ],
                'autoload' => ['psr-4' => ['Acme\\Redis\\' => 'src/']],
                'install-path' => '../acme/seaman-redis',
            ],
        ];

        $resolved = $autoloader->resolveWithDependencies(
            ['acme/seaman-redis'],
            $packages,
        );

        self::assertCount(1, $resolved);
        self::assertSame('acme/seaman-redis', $resolved[0]['name']);
    }
}
