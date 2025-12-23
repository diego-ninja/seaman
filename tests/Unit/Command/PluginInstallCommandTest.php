<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Command;

use Seaman\Command\Plugin\PluginInstallCommand;
use Seaman\Service\PackagistClient;
use Symfony\Component\Console\Tester\CommandTester;

test('plugin install command is correctly configured', function () {
    $packagist = new PackagistClient();
    $command = new PluginInstallCommand($packagist);

    expect($command->getName())->toBe('plugin:install');
    expect($command->getDescription())->toBe('Install a plugin from Packagist');

    $definition = $command->getDefinition();
    expect($definition->hasArgument('package'))->toBeTrue();
    expect($definition->hasOption('dev'))->toBeTrue();
});

test('plugin install command requires package argument', function () {
    $packagist = new PackagistClient();
    $command = new PluginInstallCommand($packagist);

    $definition = $command->getDefinition();
    $argument = $definition->getArgument('package');

    expect($argument->isRequired())->toBeTrue();
    expect($argument->getDescription())->toContain('package name');
});
