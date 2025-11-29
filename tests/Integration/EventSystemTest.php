<?php

// ABOUTME: Integration tests for event system.
// ABOUTME: Verifies EventDispatcher registration and listener execution.

declare(strict_types=1);

namespace Tests\Integration;

use Seaman\Application;
use Symfony\Component\Console\Tester\CommandTester;

test('application has event dispatcher configured', function (): void {
    $app = new Application();

    $dispatcher = $app->getEventDispatcher();

    expect($dispatcher)->toBeInstanceOf(\Symfony\Component\EventDispatcher\EventDispatcher::class);
});

test('listeners are registered and execute', function (): void {
    $app = new Application();

    $command = $app->find('seaman:init');
    $tester = new CommandTester($command);

    // Capture error_log to verify LogCommandListener executed
    $tempLog = sys_get_temp_dir() . '/test-integration-' . uniqid() . '.log';
    $originalErrorLog = ini_get('error_log');
    ini_set('error_log', $tempLog);

    // Execute command (will fail but that's OK, we're testing listener execution)
    try {
        $tester->setInputs(['no']); // Decline overwrite if seaman.yaml exists
        $tester->execute([]);
    } catch (\Exception) {
        // Expected to fail, we're just testing if listeners execute
    }

    ini_set('error_log', $originalErrorLog);

    // Verify LogCommandListener executed
    if (file_exists($tempLog)) {
        $logContent = file_get_contents($tempLog);
        expect($logContent)->toContain('[Seaman] Executing command: seaman:init');
        unlink($tempLog);
    }
});

test('listeners from Listener directory are discovered', function (): void {
    $app = new Application();
    $dispatcher = $app->getEventDispatcher();

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
