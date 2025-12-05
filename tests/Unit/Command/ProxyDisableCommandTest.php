<?php

declare(strict_types=1);

// ABOUTME: Tests for ProxyDisableCommand.
// ABOUTME: Validates proxy disable command behavior.

namespace Seaman\Tests\Unit\Command;

use Seaman\Command\ProxyDisableCommand;
use Seaman\Enum\OperatingMode;
use Seaman\Service\Container\ServiceRegistry;

test('ProxyDisableCommand exists and has correct name', function () {
    expect(class_exists(ProxyDisableCommand::class))->toBeTrue();

    $reflection = new \ReflectionClass(ProxyDisableCommand::class);
    $attributes = $reflection->getAttributes();

    $commandAttribute = null;
    foreach ($attributes as $attr) {
        if (str_contains($attr->getName(), 'AsCommand')) {
            $commandAttribute = $attr->newInstance();
            break;
        }
    }

    expect($commandAttribute)->not->toBeNull()
        ->and($commandAttribute->name)->toBe('seaman:proxy:disable|proxy:disable');
});

test('ProxyDisableCommand only supports Managed mode', function () {
    $registry = ServiceRegistry::create();
    $command = new ProxyDisableCommand($registry);

    $reflection = new \ReflectionMethod($command, 'supportsMode');
    $reflection->setAccessible(true);

    expect($reflection->invoke($command, OperatingMode::Managed))->toBeTrue()
        ->and($reflection->invoke($command, OperatingMode::Unmanaged))->toBeFalse()
        ->and($reflection->invoke($command, OperatingMode::Uninitialized))->toBeFalse();
});
