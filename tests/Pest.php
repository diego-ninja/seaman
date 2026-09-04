<?php

declare(strict_types=1);

// ABOUTME: Pest PHP test configuration file.
// ABOUTME: Sets up test environment and global expectations.

uses(
    \Seaman\Tests\TestCase::class,
)->in('Unit', 'Integration');

uses()->group('docker')->in(
    'Integration/Command/DbDumpCommandTest.php',
    'Integration/Command/DbRestoreCommandTest.php',
    'Integration/Command/DbShellCommandTest.php',
    'Integration/Command/DestroyCommandTest.php',
    'Integration/Command/ExecuteComposerCommandTest.php',
    'Integration/Command/ExecuteConsoleCommandTest.php',
    'Integration/Command/ExecutePhpCommandTest.php',
    'Integration/Command/LogsCommandTest.php',
    'Integration/Command/RebuildCommandTest.php',
    'Integration/Command/RestartCommandTest.php',
    'Integration/Command/ShellCommandTest.php',
    'Integration/Command/StartCommandTest.php',
    'Integration/Command/StatusCommandTest.php',
    'Integration/Command/StopCommandTest.php',
    'Integration/Command/XdebugCommandTest.php',
);

if (getenv('SEAMAN_DOCKER_TESTS') === '1') {
    uses()->beforeAll(function () {
        \Seaman\Tests\Integration\TestHelper::cleanupOrphanedNetworks();
    })->in('Integration');

    register_shutdown_function(function () {
        \Seaman\Tests\Integration\TestHelper::cleanupOrphanedNetworks();
    });
}
