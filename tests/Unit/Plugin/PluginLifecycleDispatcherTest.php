<?php

// ABOUTME: Tests for the PluginLifecycleDispatcher service.
// ABOUTME: Validates event dispatching, handler sorting, and execution.

declare(strict_types=1);

namespace Tests\Unit\Plugin;

use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\OnLifecycle;
use Seaman\Plugin\LifecycleEventData;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\PluginLifecycleDispatcher;
use Seaman\Plugin\PluginRegistry;

beforeEach(function (): void {
    $this->registry = new PluginRegistry();
    $this->dispatcher = new PluginLifecycleDispatcher($this->registry);
});

test('dispatches event to matching lifecycle handlers', function (): void {
    $executed = false;

    $plugin = new #[AsSeamanPlugin(name: 'test')] class ($executed) implements PluginInterface {
        public function __construct(private bool &$executed) {}

        public function getName(): string
        {
            return 'test';
        }

        public function getVersion(): string
        {
            return '1.0.0';
        }

        public function getDescription(): string
        {
            return 'Test';
        }

        #[OnLifecycle('before:start')]
        public function handleBeforeStart(LifecycleEventData $data): void
        {
            $this->executed = true;
        }
    };

    $this->registry->register($plugin, []);

    $this->dispatcher->dispatch('before:start', new LifecycleEventData(
        event: 'before:start',
        projectRoot: '/test',
    ));

    expect($executed)->toBeTrue();
});

test('does not dispatch event to non-matching handlers', function (): void {
    $executed = false;

    $plugin = new #[AsSeamanPlugin(name: 'test')] class ($executed) implements PluginInterface {
        public function __construct(private bool &$executed) {}

        public function getName(): string
        {
            return 'test';
        }

        public function getVersion(): string
        {
            return '1.0.0';
        }

        public function getDescription(): string
        {
            return 'Test';
        }

        #[OnLifecycle('before:start')]
        public function handleBeforeStart(LifecycleEventData $data): void
        {
            $this->executed = true;
        }
    };

    $this->registry->register($plugin, []);

    $this->dispatcher->dispatch('after:start', new LifecycleEventData(
        event: 'after:start',
        projectRoot: '/test',
    ));

    expect($executed)->toBeFalse();
});

test('dispatches to multiple plugins', function (): void {
    $executionLog = [];

    $plugin1 = new #[AsSeamanPlugin(name: 'plugin-1')] class ($executionLog) implements PluginInterface {
        public function __construct(private array &$executionLog) {}

        public function getName(): string
        {
            return 'plugin-1';
        }

        public function getVersion(): string
        {
            return '1.0.0';
        }

        public function getDescription(): string
        {
            return 'Plugin 1';
        }

        #[OnLifecycle('before:init')]
        public function handleBeforeInit(LifecycleEventData $data): void
        {
            $this->executionLog[] = 'plugin-1';
        }
    };

    $plugin2 = new #[AsSeamanPlugin(name: 'plugin-2')] class ($executionLog) implements PluginInterface {
        public function __construct(private array &$executionLog) {}

        public function getName(): string
        {
            return 'plugin-2';
        }

        public function getVersion(): string
        {
            return '1.0.0';
        }

        public function getDescription(): string
        {
            return 'Plugin 2';
        }

        #[OnLifecycle('before:init')]
        public function handleBeforeInit(LifecycleEventData $data): void
        {
            $this->executionLog[] = 'plugin-2';
        }
    };

    $this->registry->register($plugin1, []);
    $this->registry->register($plugin2, []);

    $this->dispatcher->dispatch('before:init', new LifecycleEventData(
        event: 'before:init',
        projectRoot: '/test',
    ));

    expect($executionLog)->toHaveCount(2)
        ->and($executionLog)->toContain('plugin-1')
        ->and($executionLog)->toContain('plugin-2');
});

test('executes handlers in priority order (higher first)', function (): void {
    $executionOrder = [];

    $plugin = new #[AsSeamanPlugin(name: 'test')] class ($executionOrder) implements PluginInterface {
        public function __construct(private array &$executionOrder) {}

        public function getName(): string
        {
            return 'test';
        }

        public function getVersion(): string
        {
            return '1.0.0';
        }

        public function getDescription(): string
        {
            return 'Test';
        }

        #[OnLifecycle('before:start', priority: 10)]
        public function highPriority(LifecycleEventData $data): void
        {
            $this->executionOrder[] = 'high';
        }

        #[OnLifecycle('before:start', priority: 5)]
        public function mediumPriority(LifecycleEventData $data): void
        {
            $this->executionOrder[] = 'medium';
        }

        #[OnLifecycle('before:start', priority: 1)]
        public function lowPriority(LifecycleEventData $data): void
        {
            $this->executionOrder[] = 'low';
        }
    };

    $this->registry->register($plugin, []);

    $this->dispatcher->dispatch('before:start', new LifecycleEventData(
        event: 'before:start',
        projectRoot: '/test',
    ));

    expect($executionOrder)->toBe(['high', 'medium', 'low']);
});

test('passes event data to handlers', function (): void {
    $receivedData = null;

    $plugin = new #[AsSeamanPlugin(name: 'test')] class ($receivedData) implements PluginInterface {
        public function __construct(private mixed &$receivedData) {}

        public function getName(): string
        {
            return 'test';
        }

        public function getVersion(): string
        {
            return '1.0.0';
        }

        public function getDescription(): string
        {
            return 'Test';
        }

        #[OnLifecycle('before:rebuild')]
        public function handleBeforeRebuild(LifecycleEventData $data): void
        {
            $this->receivedData = $data;
        }
    };

    $this->registry->register($plugin, []);

    $eventData = new LifecycleEventData(
        event: 'before:rebuild',
        projectRoot: '/my/project',
        service: 'mysql',
    );

    $this->dispatcher->dispatch('before:rebuild', $eventData);

    expect($receivedData)->toBeInstanceOf(LifecycleEventData::class)
        ->and($receivedData->event)->toBe('before:rebuild')
        ->and($receivedData->projectRoot)->toBe('/my/project')
        ->and($receivedData->service)->toBe('mysql');
});

test('handles plugins with no lifecycle handlers', function (): void {
    $plugin = new #[AsSeamanPlugin(name: 'test')] class implements PluginInterface {
        public function getName(): string
        {
            return 'test';
        }

        public function getVersion(): string
        {
            return '1.0.0';
        }

        public function getDescription(): string
        {
            return 'Test';
        }
    };

    $this->registry->register($plugin, []);

    $this->dispatcher->dispatch('before:start', new LifecycleEventData(
        event: 'before:start',
        projectRoot: '/test',
    ));

    expect(true)->toBeTrue(); // No exceptions = success
});
