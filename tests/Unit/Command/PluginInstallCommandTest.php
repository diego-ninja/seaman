<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Command;

use Seaman\Command\Plugin\PluginInstallCommand;
use Seaman\Plugin\PluginRegistry;
use Seaman\Service\PackagistClient;

beforeEach(function () {
    $this->testDir = sys_get_temp_dir() . '/seaman-plugin-install-test-' . uniqid();
    mkdir($this->testDir, 0755, true);
});

afterEach(function () {
    if (isset($this->testDir) && is_dir($this->testDir)) {
        exec("rm -rf {$this->testDir}");
    }
});

test('plugin install command is correctly configured', function () {
    $packagist = new PackagistClient();
    $registry = PluginRegistry::discover(
        projectRoot: $this->testDir,
        localPluginsDir: $this->testDir . '/plugins',
        pluginConfig: [],
    );
    $command = new PluginInstallCommand($packagist, $registry);

    expect($command->getName())->toBe('plugin:install');
    expect($command->getDescription())->toBe('Install plugins from Packagist');

    $definition = $command->getDefinition();
    expect($definition->hasArgument('package'))->toBeTrue();
    expect($definition->hasOption('dev'))->toBeTrue();
});

test('plugin install command has optional package argument', function () {
    $packagist = new PackagistClient();
    $registry = PluginRegistry::discover(
        projectRoot: $this->testDir,
        localPluginsDir: $this->testDir . '/plugins',
        pluginConfig: [],
    );
    $command = new PluginInstallCommand($packagist, $registry);

    $definition = $command->getDefinition();
    $argument = $definition->getArgument('package');

    expect($argument->isRequired())->toBeFalse();
    expect($argument->getDescription())->toContain('package name');
});
