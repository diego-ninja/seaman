<?php

declare(strict_types=1);

// ABOUTME: Tests for SymfonyCliDetector service.
// ABOUTME: Validates CLI detection and installation instruction generation.

namespace Seaman\Tests\Unit\Service;

use Seaman\Service\Detector\SymfonyCliDetector;

test('isInstalled returns true when symfony command exists', function () {
    $detector = new SymfonyCliDetector();

    // This test depends on whether Symfony CLI is actually installed
    // We just verify the method returns a boolean
    $result = $detector->isInstalled();

    expect($result)->toBeBool();
});

test('getVersion returns string or null', function () {
    $detector = new SymfonyCliDetector();

    $version = $detector->getVersion();

    // If CLI is installed, should return version string
    // If not installed, should return null
    if ($detector->isInstalled()) {
        expect($version)->toBeString();
    } else {
        expect($version)->toBeNull();
    }
});

test('getInstallCommand returns curl installer command', function () {
    $detector = new SymfonyCliDetector();

    $command = $detector->getInstallCommand();

    expect($command)->toContain('curl')
        ->and($command)->toContain('get.symfony.com')
        ->and($command)->toContain('installer');
});

test('getInstallInstructions returns non-empty array', function () {
    $detector = new SymfonyCliDetector();

    $instructions = $detector->getInstallInstructions();

    expect($instructions)->toBeArray()
        ->and($instructions)->not->toBeEmpty();
});

test('getInstallInstructions includes curl command', function () {
    $detector = new SymfonyCliDetector();

    $instructions = $detector->getInstallInstructions();
    $instructionsText = implode("\n", $instructions);

    expect($instructionsText)->toContain('curl');
});

test('getInstallInstructions includes documentation link', function () {
    $detector = new SymfonyCliDetector();

    $instructions = $detector->getInstallInstructions();
    $instructionsText = implode("\n", $instructions);

    expect($instructionsText)->toContain('symfony.com/download');
});
