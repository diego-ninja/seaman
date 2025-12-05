<?php

declare(strict_types=1);

// ABOUTME: Tests for ProxyEnableCommand.
// ABOUTME: Validates proxy enable command behavior.

namespace Seaman\Tests\Unit\Command;

use Seaman\Command\ProxyEnableCommand;
use Seaman\Enum\OperatingMode;
use Seaman\Service\Container\ServiceRegistry;

test('ProxyEnableCommand exists and has correct name', function () {
    expect(class_exists(ProxyEnableCommand::class))->toBeTrue();

    $reflection = new \ReflectionClass(ProxyEnableCommand::class);
    $attributes = $reflection->getAttributes();

    $commandAttribute = null;
    foreach ($attributes as $attr) {
        if (str_contains($attr->getName(), 'AsCommand')) {
            $commandAttribute = $attr->newInstance();
            break;
        }
    }

    expect($commandAttribute)->not->toBeNull()
        ->and($commandAttribute->name)->toBe('seaman:proxy:enable|proxy:enable');
});

test('ProxyEnableCommand only supports Managed mode', function () {
    $registry = ServiceRegistry::create();
    $command = new ProxyEnableCommand($registry);

    $reflection = new \ReflectionMethod($command, 'supportsMode');
    $reflection->setAccessible(true);

    expect($reflection->invoke($command, OperatingMode::Managed))->toBeTrue()
        ->and($reflection->invoke($command, OperatingMode::Unmanaged))->toBeFalse()
        ->and($reflection->invoke($command, OperatingMode::Uninitialized))->toBeFalse();
});
