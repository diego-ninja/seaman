<?php

declare(strict_types=1);

/**
 * @property string $tempDir
 */

// ABOUTME: Integration tests for InitCommand.
// ABOUTME: Validates interactive initialization flow.

namespace Seaman\Tests\Integration\Command;

use Seaman\Application;
use Seaman\Command\InitCommand;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/seaman-test-' . uniqid();
    mkdir($this->tempDir, 0755, true);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        exec("rm -rf {$this->tempDir}");
    }
});

test('init command creates seaman.yaml', function () {
    $application = new Application();

    $commandTester = new CommandTester($application->find('init'));
    $commandTester->setInputs(['8.4', 'symfony', 'postgresql', '', '']); // Simulate user inputs

    $commandTester->execute([]);

    expect($commandTester->getStatusCode())->toBe(0);
    // Will validate files after implementation
});
