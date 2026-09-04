<?php

declare(strict_types=1);

// ABOUTME: Unit tests for the shared POSIX signal handler.
// ABOUTME: Verifies registration and restoration without sending process signals.

namespace Seaman\Tests\Unit\Signal;

use Innmind\Signals\Info;
use Innmind\Signals\Signal;
use Seaman\Signal\SignalHandler;

beforeEach(function () {
    $reflection = new \ReflectionClass(SignalHandler::class);
    $instance = $reflection->getProperty('instance');
    $listeners = $reflection->getProperty('listeners');

    $this->instanceProperty = $instance;
    $this->listenersProperty = $listeners;
    $this->previousInstance = $instance->getValue();
    $this->previousListeners = $listeners->getValue();
    $this->previousSignalHandlers = [
        SIGUSR1 => pcntl_signal_get_handler(SIGUSR1),
        SIGUSR2 => pcntl_signal_get_handler(SIGUSR2),
    ];
    $this->previousAsyncSignals = pcntl_async_signals();
    $this->registeredListeners = [];
});

afterEach(function () {
    $handler = SignalHandler::getInstance()->handler;
    foreach ($this->registeredListeners as $listener) {
        $_ = $handler->remove($listener)->unwrap();
    }

    $this->instanceProperty->setValue(null, $this->previousInstance);
    $this->listenersProperty->setValue(null, $this->previousListeners);

    foreach ($this->previousSignalHandlers as $signal => $previousHandler) {
        pcntl_signal($signal, $previousHandler);
    }
    pcntl_async_signals($this->previousAsyncSignals);
});

test('returns the same singleton instance', function () {
    expect(SignalHandler::getInstance())->toBe(SignalHandler::getInstance());
});

test('registers a listener for a user-defined signal', function () {
    $received = [];
    $listener = function (Signal $signal, Info $info) use (&$received): void {
        $received[] = $signal;
    };
    $this->registeredListeners[] = $listener;

    SignalHandler::listen([Signal::userDefinedSignal1], $listener);

    $installedHandler = pcntl_signal_get_handler(SIGUSR1);
    expect(is_callable($installedHandler))->toBeTrue();
    $installedHandler(SIGUSR1, []);

    expect($received)->toBe([Signal::userDefinedSignal1]);
});

test('restores a tracked listener after it is removed from the handler', function () {
    $received = [];
    $listener = function (Signal $signal, Info $info) use (&$received): void {
        $received[] = $signal;
    };
    $this->registeredListeners[] = $listener;

    SignalHandler::listen([Signal::userDefinedSignal2], $listener);
    $_ = SignalHandler::getInstance()->handler->remove($listener)->unwrap();

    SignalHandler::restore();

    $restoredHandler = pcntl_signal_get_handler(SIGUSR2);
    expect(is_callable($restoredHandler))->toBeTrue();
    $restoredHandler(SIGUSR2, []);

    expect($received)->toBe([Signal::userDefinedSignal2]);
});
