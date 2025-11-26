<?php

declare(strict_types=1);

// ABOUTME: Integration tests for RebuildCommand.
// ABOUTME: Validates image rebuilding functionality.

namespace Tests\Integration\Command;

use Symfony\Component\Console\Tester\CommandTester;
use Seaman\Command\RebuildCommand;

beforeEach(function (): void {
    $this->testDir = sys_get_temp_dir() . '/seaman-rebuild-test-' . uniqid();
    mkdir($this->testDir, 0755, true);
    mkdir($this->testDir . '/.seaman', 0755, true);
    chdir($this->testDir);

    // Create minimal seaman.yaml
    file_put_contents($this->testDir . '/seaman.yaml', 'version: 1.0');

    // Create minimal Dockerfile
    file_put_contents(
        $this->testDir . '/.seaman/Dockerfile',
        "FROM ubuntu:24.04\nRUN echo 'test'",
    );

    // Create minimal docker-compose.yml
    file_put_contents(
        $this->testDir . '/docker-compose.yml',
        "version: '3.8'\nservices:\n  app:\n    image: seaman/seaman:latest",
    );
});

afterEach(function (): void {
    if (is_dir($this->testDir)) {
        exec("rm -rf {$this->testDir}");
    }
});

test('rebuild command requires seaman.yaml', function (): void {
    chdir(sys_get_temp_dir());

    $command = new RebuildCommand();
    $tester = new CommandTester($command);
    $tester->execute([]);

    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain('seaman.yaml not found');
});

test('rebuild command builds image and restarts services', function (): void {
    $command = new RebuildCommand();
    $tester = new CommandTester($command);

    $result = $tester->execute([]);

    $output = $tester->getDisplay();
    expect($output)->toContain('Building Docker image');
});
