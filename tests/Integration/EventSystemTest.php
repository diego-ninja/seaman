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

    // Verify at least LogCommandListener is registered
    $hasLogListener = false;
    foreach ($listeners as $listener) {
        if ($listener instanceof \Seaman\Listener\LogCommandListener) {
            $hasLogListener = true;
            break;
        }
    }

    expect($hasLogListener)->toBeTrue();
});
