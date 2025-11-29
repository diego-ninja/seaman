<?php

// ABOUTME: Tests for ListenerDiscovery service.
// ABOUTME: Verifies listener scanning and metadata extraction.

declare(strict_types=1);

namespace Tests\Unit\EventListener;

use Seaman\EventListener\ListenerDiscovery;
use Seaman\Attribute\AsEventListener;
use Symfony\Component\Console\ConsoleEvents;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir() . '/listener-test-' . uniqid();
    mkdir($this->tempDir);
});

afterEach(function (): void {
    if (is_dir($this->tempDir)) {
        array_map('unlink', glob($this->tempDir . '/*.php') ?: []);
        rmdir($this->tempDir);
    }
});

test('discovers listener with attribute', function (): void {
    // Create a test listener file
    $listenerCode = <<<'PHP'
<?php
namespace Test;
use Seaman\Attribute\AsEventListener;
use Symfony\Component\Console\ConsoleEvents;

#[AsEventListener(event: ConsoleEvents::COMMAND, priority: 100)]
class TestListener {
    public function __invoke($event): void {}
}
PHP;

    file_put_contents($this->tempDir . '/TestListener.php', $listenerCode);

    $discovery = new ListenerDiscovery($this->tempDir);
    $listeners = $discovery->discover();

    expect($listeners)->toHaveCount(1);
    expect($listeners[0]->className)->toBe('Test\\TestListener');
    expect($listeners[0]->event)->toBe(ConsoleEvents::COMMAND);
    expect($listeners[0]->priority)->toBe(100);
});

test('ignores classes without attribute', function (): void {
    $classCode = <<<'PHP'
<?php
namespace Test;

class RegularClass {
    public function method(): void {}
}
PHP;

    file_put_contents($this->tempDir . '/RegularClass.php', $classCode);

    $discovery = new ListenerDiscovery($this->tempDir);
    $listeners = $discovery->discover();

    expect($listeners)->toBeEmpty();
});

test('sorts listeners by priority descending', function (): void {
    $listener1 = <<<'PHP'
<?php
namespace Test;
use Seaman\Attribute\AsEventListener;

#[AsEventListener(event: 'test', priority: 50)]
class Listener1 {
    public function __invoke($event): void {}
}
PHP;

    $listener2 = <<<'PHP'
<?php
namespace Test;
use Seaman\Attribute\AsEventListener;

#[AsEventListener(event: 'test', priority: 100)]
class Listener2 {
    public function __invoke($event): void {}
}
PHP;

    file_put_contents($this->tempDir . '/Listener1.php', $listener1);
    file_put_contents($this->tempDir . '/Listener2.php', $listener2);

    $discovery = new ListenerDiscovery($this->tempDir);
    $listeners = $discovery->discover();

    expect($listeners)->toHaveCount(2);
    expect($listeners[0]->priority)->toBe(100); // Higher priority first
    expect($listeners[1]->priority)->toBe(50);
});

test('handles empty directory', function (): void {
    $discovery = new ListenerDiscovery($this->tempDir);
    $listeners = $discovery->discover();

    expect($listeners)->toBeEmpty();
});
