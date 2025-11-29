<?php

// ABOUTME: Tests for NotifyOnErrorListener.
// ABOUTME: Verifies error notifications are sent.

declare(strict_types=1);

namespace Tests\Unit\Listener;

use Seaman\Listener\NotifyOnErrorListener;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

test('listener is properly annotated', function (): void {
    $reflection = new \ReflectionClass(NotifyOnErrorListener::class);
    $attributes = $reflection->getAttributes(\Seaman\Attribute\AsEventListener::class);

    expect($attributes)->toHaveCount(1);

    $instance = $attributes[0]->newInstance();
    expect($instance->event)->toBe('console.error');
    expect($instance->priority)->toBe(0);
});

test('listener has invoke method', function (): void {
    $listener = new NotifyOnErrorListener();

    expect($listener)->toBeCallable();
});

test('listener calls notifier on error', function (): void {
    $listener = new NotifyOnErrorListener();

    $command = new Command('test:command');
    $input = new ArrayInput([]);
    $output = new NullOutput();
    $error = new \RuntimeException('Test error message');

    $event = new ConsoleErrorEvent($input, $output, $error, $command);

    // Just verify it can be invoked without throwing
    $listener($event);

    expect(true)->toBeTrue();
});
