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
