<?php

// ABOUTME: Tests for Symfony application detection service.
// ABOUTME: Verifies detection logic using multiple indicators.

declare(strict_types=1);

namespace Tests\Unit\Service;

use Seaman\Service\Detector\SymfonyDetector;

test('detects symfony when all indicators present', function (): void {
    $detector = new SymfonyDetector();

    $tempDir = sys_get_temp_dir() . '/test-symfony-' . uniqid();
    mkdir($tempDir);
    mkdir($tempDir . '/config');
    mkdir($tempDir . '/src');
    mkdir($tempDir . '/bin');

    file_put_contents($tempDir . '/composer.json', json_encode([
        'require' => ['symfony/framework-bundle' => '^7.0'],
    ]));
    file_put_contents($tempDir . '/bin/console', '#!/usr/bin/env php');
    chmod($tempDir . '/bin/console', 0755);
    file_put_contents($tempDir . '/src/Kernel.php', '<?php class Kernel {}');

    $result = $detector->detect($tempDir);

    expect($result->isSymfonyProject)->toBeTrue();
    expect($result->matchedIndicators)->toBe(4);

    // Cleanup
    unlink($tempDir . '/bin/console');
    unlink($tempDir . '/composer.json');
    unlink($tempDir . '/src/Kernel.php');
    rmdir($tempDir . '/bin');
    rmdir($tempDir . '/config');
    rmdir($tempDir . '/src');
    rmdir($tempDir);
});

test('detects symfony with 2-3 indicators', function (): void {
    $detector = new SymfonyDetector();

    $tempDir = sys_get_temp_dir() . '/test-symfony-partial-' . uniqid();
    mkdir($tempDir);
    mkdir($tempDir . '/config');
    mkdir($tempDir . '/bin');

    file_put_contents($tempDir . '/composer.json', json_encode([
        'require' => ['symfony/framework-bundle' => '^7.0'],
    ]));
    file_put_contents($tempDir . '/bin/console', '#!/usr/bin/env php');
    chmod($tempDir . '/bin/console', 0755);

    $result = $detector->detect($tempDir);

    expect($result->isSymfonyProject)->toBeTrue();
    expect($result->matchedIndicators)->toBe(3);

    // Cleanup
    unlink($tempDir . '/bin/console');
    unlink($tempDir . '/composer.json');
    rmdir($tempDir . '/bin');
    rmdir($tempDir . '/config');
    rmdir($tempDir);
});

test('does not detect symfony with only 1 indicator', function (): void {
    $detector = new SymfonyDetector();

    $tempDir = sys_get_temp_dir() . '/test-not-symfony-' . uniqid();
    mkdir($tempDir);

    file_put_contents($tempDir . '/composer.json', json_encode([
        'require' => ['some/package' => '^1.0'],
    ]));

    $result = $detector->detect($tempDir);

    expect($result->isSymfonyProject)->toBeFalse();
    expect($result->matchedIndicators)->toBe(0);

    // Cleanup
    unlink($tempDir . '/composer.json');
    rmdir($tempDir);
});

test('does not detect symfony in empty directory', function (): void {
    $detector = new SymfonyDetector();

    $tempDir = sys_get_temp_dir() . '/test-empty-' . uniqid();
    mkdir($tempDir);

    $result = $detector->detect($tempDir);

    expect($result->isSymfonyProject)->toBeFalse();
    expect($result->matchedIndicators)->toBe(0);

    rmdir($tempDir);
});
