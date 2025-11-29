<?php

// ABOUTME: Tests for LogCommandListener.
// ABOUTME: Verifies command execution logging.

declare(strict_types=1);

namespace Tests\Unit\Listener;

use Seaman\Listener\LogCommandListener;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

test('listener logs command name', function (): void {
    $command = new Command('test:command');
    $input = new ArrayInput([]);
    $output = new NullOutput();

    $event = new ConsoleCommandEvent($command, $input, $output);

    $listener = new LogCommandListener();

    // Capture error_log output
    $originalErrorLog = ini_get('error_log');
    $tempLog = sys_get_temp_dir() . '/test-log-' . uniqid() . '.log';
    ini_set('error_log', $tempLog);

    $listener($event);

    ini_set('error_log', $originalErrorLog);

    $logContent = file_get_contents($tempLog);
    expect($logContent)->toContain('[Seaman] Executing command: test:command');

    unlink($tempLog);
});

test('listener handles null command gracefully', function (): void {
    $input = new ArrayInput([]);
    $output = new NullOutput();

    $event = new ConsoleCommandEvent(null, $input, $output);

    $listener = new LogCommandListener();

    $tempLog = sys_get_temp_dir() . '/test-log-' . uniqid() . '.log';
    ini_set('error_log', $tempLog);

    $listener($event);

    $logContent = file_get_contents($tempLog);
    expect($logContent)->toContain('[Seaman] Executing command: unknown');

    unlink($tempLog);
});
