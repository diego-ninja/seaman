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
}
