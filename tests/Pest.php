<?php

declare(strict_types=1);

// ABOUTME: Pest PHP test configuration file.
// ABOUTME: Sets up test environment and global expectations.

uses(
    \Seaman\Tests\TestCase::class,
)->in('Unit', 'Integration');

if (getenv('SEAMAN_DOCKER_TESTS') === '1') {
    uses()->beforeAll(function () {
        \Seaman\Tests\Integration\TestHelper::cleanupOrphanedNetworks();
    })->in('Integration');

    register_shutdown_function(function () {
        \Seaman\Tests\Integration\TestHelper::cleanupOrphanedNetworks();
    });
}
