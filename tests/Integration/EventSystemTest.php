<?php

// ABOUTME: Integration tests for event system.
// ABOUTME: Verifies EventDispatcher registration and listener execution.

declare(strict_types=1);

namespace Tests\Integration;

use Seaman\Application;
use Symfony\Component\Console\Tester\CommandTester;

test('application has event dispatcher configured', function (): void {
    $app = new Application();

    $dispatcher = $app->eventDispatcher;

    expect($dispatcher)->toBeInstanceOf(\Symfony\Component\EventDispatcher\EventDispatcher::class);
});

test('listeners from Listener directory are discovered', function (): void {
    $app = new Application();
    $dispatcher = $app->eventDispatcher;

    // Verify listeners are registered
    $listeners = $dispatcher->getListeners('console.command');

    expect($listeners)->not->toBeEmpty();

    // Verify at least CommandDecorationListener is registered
    $hasListener = false;
    foreach ($listeners as $listener) {
        if (is_array($listener) && isset($listener[0]) && $listener[0] instanceof \Seaman\Listener\CommandDecorationListener) {
            $hasListener = true;
            break;
        }
    }

    expect($hasListener)->toBeTrue();
});
