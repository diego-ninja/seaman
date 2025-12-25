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
}
