<?php

// ABOUTME: Tests for EventListenerMetadata value object.
// ABOUTME: Verifies metadata contains all required information.

declare(strict_types=1);

namespace Tests\Unit\EventListener;

use Seaman\EventListener\EventListenerMetadata;
use Symfony\Component\Console\ConsoleEvents;

test('metadata can be created with all properties', function (): void {
    $metadata = new EventListenerMetadata(
        className: 'App\\Listener\\MyListener',
        event: ConsoleEvents::COMMAND,
        priority: 100
    );

    expect($metadata->className)->toBe('App\\Listener\\MyListener');
    expect($metadata->event)->toBe(ConsoleEvents::COMMAND);
    expect($metadata->priority)->toBe(100);
});

test('metadata is readonly', function (): void {
    $reflection = new \ReflectionClass(EventListenerMetadata::class);

    expect($reflection->isReadOnly())->toBeTrue();
});
