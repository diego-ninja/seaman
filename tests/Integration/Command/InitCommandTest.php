<?php

// ABOUTME: Integration tests for InitCommand.
// ABOUTME: Tests command instantiation and service integration.

declare(strict_types=1);

namespace Tests\Integration\Command;

use Seaman\Application;
use Seaman\Enum\ProjectType;
use Seaman\Service\Detector\SymfonyDetector;
use Seaman\Service\SymfonyProjectBootstrapper;
use Seaman\UI\HeadlessMode;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function (): void {
    HeadlessMode::reset();
    $this->tempDir = sys_get_temp_dir() . '/seaman-init-test-' . uniqid();
    $this->originalDir = getcwd();
});

afterEach(function (): void {
    HeadlessMode::reset();
    if (isset($this->tempDir) && is_string($this->tempDir) && is_dir($this->tempDir)) {
        if (isset($this->originalDir) && is_string($this->originalDir)) {
            chdir($this->originalDir);
        }
        exec('rm -rf ' . escapeshellarg($this->tempDir));
    }
});

test('init command is registered in application', function (): void {
    $app = new Application();
    $command = $app->find('init');

    expect($command)->toBeInstanceOf(\Seaman\Command\InitCommand::class);
    expect($command->getName())->toBe('seaman:init');
});

test('init command has correct aliases', function (): void {
    $app = new Application();
    $command = $app->find('init');

    expect($command->getAliases())->toContain('init');
});

test('symfony detector works correctly', function (): void {
    $detector = new SymfonyDetector();

    $tempDir = sys_get_temp_dir() . '/test-detector-' . uniqid();
    mkdir($tempDir);

    $result = $detector->detect($tempDir);
    expect($result->isSymfonyProject)->toBeFalse();

    rmdir($tempDir);
});

test('project bootstrapper generates correct commands', function (): void {
    $bootstrapper = new SymfonyProjectBootstrapper();

    $command = $bootstrapper->getBootstrapCommand(
        ProjectType::WebApplication,
        'test-app',
        '/tmp',
    );

    expect($command)->toContain('symfony');
    expect($command)->toContain('new');
    expect($command)->toContain('test-app');
});

test('init command creates configuration with preset responses', function (): void {
    // Create minimal Symfony project structure
    assert(is_string($this->tempDir));
    mkdir($this->tempDir, 0755, true);
    chdir($this->tempDir);

    file_put_contents($this->tempDir . '/composer.json', json_encode([
        'require' => ['symfony/framework-bundle' => '^7.0'],
    ]));
    mkdir($this->tempDir . '/src');
    mkdir($this->tempDir . '/config');

    HeadlessMode::enable();
    HeadlessMode::preset([
        'Select PHP version (default: 8.4)' => '8.4',
        'Select database (default: postgresql)' => 'mysql',
        'Select additional services' => ['redis'],
        'Do you want to enable Xdebug?' => false,
        'Use Traefik as reverse proxy?' => false,
        'Do you want to enable DevContainer support?' => false,
        'Continue with this configuration?' => true,
    ]);

    $app = new Application();
    $tester = new CommandTester($app->find('init'));
    $tester->execute([]);

    expect(file_exists($this->tempDir . '/.seaman/seaman.yaml'))->toBeTrue();
});
