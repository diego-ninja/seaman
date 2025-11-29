<?php

// ABOUTME: Tests for AsEventListener attribute.
// ABOUTME: Verifies attribute can be created and read via reflection.

declare(strict_types=1);

namespace Tests\Unit\Attribute;

use Seaman\Attribute\AsEventListener;
use Symfony\Component\Console\ConsoleEvents;

test('attribute can be instantiated with event and priority', function (): void {
    $attribute = new AsEventListener(
        event: ConsoleEvents::COMMAND,
        priority: 100
    );

    expect($attribute->event)->toBe(ConsoleEvents::COMMAND);
    expect($attribute->priority)->toBe(100);
});

test('attribute has default priority of 0', function (): void {
    $attribute = new AsEventListener(event: ConsoleEvents::COMMAND);

    expect($attribute->priority)->toBe(0);
});

test('attribute can be read from class via reflection', function (): void {
    $class = new \ReflectionClass(TestListener::class);
    $attributes = $class->getAttributes(AsEventListener::class);

    expect($attributes)->toHaveCount(1);

    $instance = $attributes[0]->newInstance();
    expect($instance)->toBeInstanceOf(AsEventListener::class);
    expect($instance->event)->toBe(ConsoleEvents::TERMINATE);
    expect($instance->priority)->toBe(50);
});

#[\Seaman\Attribute\AsEventListener(event: ConsoleEvents::TERMINATE, priority: 50)]
class TestListener
{
    public function __invoke($event): void
    {
    }
}
