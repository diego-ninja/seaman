<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Command;

use Seaman\Command\Plugin\PluginInstallCommand;
use Seaman\Plugin\PluginRegistry;
use Seaman\Service\PackagistClient;

test('plugin install command is correctly configured', function (): void {
    $testDir = sys_get_temp_dir() . '/seaman-plugin-install-test-' . uniqid();
    mkdir($testDir, 0755, true);

    try {
        $packagist = new PackagistClient();
        $registry = PluginRegistry::discover(
            projectRoot: $testDir,
            localPluginsDir: $testDir . '/plugins',
            pluginConfig: [],
        );
        $command = new PluginInstallCommand($packagist, $registry, $testDir);

        expect($command->getName())->toBe('plugin:install');
        expect($command->getDescription())->toBe('Install plugins from Packagist');

        $definition = $command->getDefinition();
        expect($definition->hasArgument('package'))->toBeTrue();
        expect($definition->hasOption('dev'))->toBeTrue();
    } finally {
        exec("rm -rf {$testDir}");
    }
});

test('plugin install command has optional package argument', function (): void {
    $testDir = sys_get_temp_dir() . '/seaman-plugin-install-test-' . uniqid();
    mkdir($testDir, 0755, true);

    try {
        $packagist = new PackagistClient();
        $registry = PluginRegistry::discover(
            projectRoot: $testDir,
            localPluginsDir: $testDir . '/plugins',
            pluginConfig: [],
        );
        $command = new PluginInstallCommand($packagist, $registry, $testDir);

        $definition = $command->getDefinition();
        $argument = $definition->getArgument('package');

        expect($argument->isRequired())->toBeFalse();
        expect($argument->getDescription())->toContain('package name');
    } finally {
        exec("rm -rf {$testDir}");
    }
});

test('plugin install fails when no composer.json exists', function (): void {
    $projectDir = sys_get_temp_dir() . '/seaman-no-composer-' . uniqid();
    mkdir($projectDir, 0755, true);

    try {
        $packagist = new PackagistClient();
        $registry = PluginRegistry::discover(
            projectRoot: $projectDir,
            localPluginsDir: $projectDir . '/plugins',
            pluginConfig: [],
        );
        $command = new PluginInstallCommand($packagist, $registry, $projectDir);

        $input = new \Symfony\Component\Console\Input\ArrayInput([
            'package' => 'vendor/seaman-plugin-test',
        ]);
        $output = new \Symfony\Component\Console\Output\BufferedOutput();

        $exitCode = $command->run($input, $output);
        $outputContent = $output->fetch();

        expect($exitCode)->toBe(\Symfony\Component\Console\Command\Command::FAILURE);
        expect($outputContent)->toContain('No composer.json found');
        expect($outputContent)->toContain('Run this command from your Symfony project directory');
    } finally {
        exec("rm -rf {$projectDir}");
    }
});
