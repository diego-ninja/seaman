<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Loader;

use Seaman\Plugin\Loader\PluginLoaderInterface;

test('PluginLoaderInterface defines load method', function (): void {
    $reflection = new \ReflectionClass(PluginLoaderInterface::class);

    expect($reflection->isInterface())->toBeTrue();
    expect($reflection->hasMethod('load'))->toBeTrue();

    $method = $reflection->getMethod('load');
    $returnType = $method->getReturnType();
    expect($returnType)->not->toBeNull();
    expect($returnType instanceof \ReflectionNamedType)->toBeTrue();
    if ($returnType instanceof \ReflectionNamedType) {
        expect($returnType->getName())->toBe('array');
    }
});
