<?php

declare(strict_types=1);

// ABOUTME: Tests for PluginInterface contract.
// ABOUTME: Validates plugin interface defines required methods.

namespace Seaman\Tests\Unit\Plugin;

use Seaman\Plugin\PluginInterface;

test('PluginInterface defines required methods', function (): void {
    $reflection = new \ReflectionClass(PluginInterface::class);

    expect($reflection->isInterface())->toBeTrue();
    expect($reflection->hasMethod('getName'))->toBeTrue();
    expect($reflection->hasMethod('getVersion'))->toBeTrue();
    expect($reflection->hasMethod('getDescription'))->toBeTrue();
});
