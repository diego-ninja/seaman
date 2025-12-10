<?php

declare(strict_types=1);

// ABOUTME: Pest PHP test configuration file.
// ABOUTME: Sets up test environment and global expectations.

uses(
    \Seaman\Tests\TestCase::class,
)->in('Unit', 'Integration');

// Clean up orphaned Docker resources before each integration test file runs
// This ensures any leftover resources from previous test runs are cleaned
uses()->beforeAll(function () {
    \Seaman\Tests\Integration\TestHelper::cleanupOrphanedNetworks();
})->in('Integration');

// Register shutdown function to clean up any remaining Docker resources
// This runs after all tests complete, ensuring a clean state
register_shutdown_function(function () {
    \Seaman\Tests\Integration\TestHelper::cleanupOrphanedNetworks();
});
