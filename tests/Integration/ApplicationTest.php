<?php

// ABOUTME: Integration tests for Symfony Console Application bootstrap.
// ABOUTME: Verifies Application instantiation, configuration, and CLI execution.

declare(strict_types=1);

use Seaman\Application;

test('Application can be instantiated', function (): void {
    $application = new Application();

    expect($application)->toBeInstanceOf(Application::class);
});

test('Application has correct name', function (): void {
    $application = new Application();

    // Name includes mode suffix when not in managed mode
    expect($application->getName())->toContain('🔱 Seaman');
});

test('Application has correct version', function (): void {
    $application = new Application();

    expect($application->getVersion())->toBe('1.0.0-beta');
});

test('bin/seaman is executable and runs', function (): void {
    $binPath = __DIR__ . '/../../bin/seaman';

    expect(file_exists($binPath))->toBeTrue('bin/seaman should exist');
    expect(is_executable($binPath))->toBeTrue('bin/seaman should be executable');

    // Execute and capture output
    exec("php {$binPath} --version 2>&1", $output, $exitCode);
    $outputString = implode("\n", $output);

    expect($exitCode)->toBe(0, 'Should exit with code 0');
    expect($outputString)->toContain('Seaman');
    expect($outputString)->toContain('1.0.0');
});
