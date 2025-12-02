<?php

declare(strict_types=1);

namespace Seaman\Tests\Integration;

use Seaman\Application;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    $this->testDir = sys_get_temp_dir() . '/seaman-devcontainer-test-' . uniqid();
    mkdir($this->testDir);
    chdir($this->testDir);
});

afterEach(function () {
    if (isset($this->testDir) && is_dir($this->testDir)) {
        exec("rm -rf {$this->testDir}");
    }
});

test('devcontainer:generate creates valid JSON configuration', function () {
    // Setup: Create seaman.yaml and docker-compose.yml
    mkdir('.seaman');
    file_put_contents(
        '.seaman/seaman.yaml',
        <<<YAML
version: "1.0"
project_type: "existing"
php:
  version: "8.4"
  xdebug:
    enabled: true
    ide_key: "VSCODE"
    client_host: "host.docker.internal"
services:
  postgresql:
    enabled: true
    type: "postgresql"
    version: "16"
    port: 5432
  redis:
    enabled: true
    type: "redis"
    version: "7-alpine"
    port: 6379
volumes:
  persist:
    - postgresql
    - redis
YAML,
    );

    file_put_contents('docker-compose.yml', 'services: {}');

    // Execute command
    $app = new Application();
    $command = $app->find('devcontainer:generate');
    $tester = new CommandTester($command);
    $tester->setInputs(['']); // Don't overwrite (file doesn't exist yet)
    $tester->execute([]);

    // Verify files created
    expect(file_exists('.devcontainer/devcontainer.json'))->toBeTrue()
        ->and(file_exists('.devcontainer/README.md'))->toBeTrue();

    // Verify JSON is valid
    $json = file_get_contents('.devcontainer/devcontainer.json');
    $decoded = json_decode($json, true);

    expect($decoded)->toBeArray()
        ->and($decoded['name'] ?? null)->not->toBeNull()
        ->and($decoded['dockerComposeFile'])->toBe('../docker-compose.yml')
        ->and($decoded['service'])->toBe('app')
        ->and($decoded['workspaceFolder'])->toBe('/var/www/html');
});

test('devcontainer configuration includes database extensions when database enabled', function () {
    mkdir('.seaman');
    file_put_contents(
        '.seaman/seaman.yaml',
        <<<YAML
version: "1.0"
project_type: "existing"
php:
  version: "8.4"
  xdebug:
    enabled: false
    ide_key: "VSCODE"
    client_host: "host.docker.internal"
services:
  postgresql:
    enabled: true
    type: "postgresql"
    version: "16"
    port: 5432
volumes:
  persist: []
YAML,
    );

    file_put_contents('docker-compose.yml', 'services: {}');

    $app = new Application();
    $command = $app->find('devcontainer:generate');
    $tester = new CommandTester($command);
    $tester->setInputs(['']);
    $tester->execute([]);

    $json = file_get_contents('.devcontainer/devcontainer.json');
    $decoded = json_decode($json, true);

    $extensions = $decoded['customizations']['vscode']['extensions'] ?? [];

    expect($extensions)->toContain('cweijan.vscode-database-client2');
});

test('devcontainer configuration includes redis extension when redis enabled', function () {
    mkdir('.seaman');
    file_put_contents(
        '.seaman/seaman.yaml',
        <<<YAML
version: "1.0"
project_type: "existing"
php:
  version: "8.4"
  xdebug:
    enabled: false
    ide_key: "VSCODE"
    client_host: "host.docker.internal"
services:
  redis:
    enabled: true
    type: "redis"
    version: "7-alpine"
    port: 6379
volumes:
  persist: []
YAML,
    );

    file_put_contents('docker-compose.yml', 'services: {}');

    $app = new Application();
    $command = $app->find('devcontainer:generate');
    $tester = new CommandTester($command);
    $tester->setInputs(['']);
    $tester->execute([]);

    $json = file_get_contents('.devcontainer/devcontainer.json');
    $decoded = json_decode($json, true);

    $extensions = $decoded['customizations']['vscode']['extensions'] ?? [];

    expect($extensions)->toContain('cisco.redis-xplorer');
});

test('devcontainer configuration includes API Platform extension when project type is api', function () {
    mkdir('.seaman');
    file_put_contents(
        '.seaman/seaman.yaml',
        <<<YAML
version: "1.0"
project_type: "api"
php:
  version: "8.4"
  xdebug:
    enabled: false
    ide_key: "VSCODE"
    client_host: "host.docker.internal"
services: {}
volumes:
  persist: []
YAML,
    );

    file_put_contents('docker-compose.yml', 'services: {}');

    $app = new Application();
    $command = $app->find('devcontainer:generate');
    $tester = new CommandTester($command);
    $tester->setInputs(['']);
    $tester->execute([]);

    $json = file_get_contents('.devcontainer/devcontainer.json');
    $decoded = json_decode($json, true);

    $extensions = $decoded['customizations']['vscode']['extensions'] ?? [];

    expect($extensions)->toContain('42crunch.vscode-openapi');
});

test('devcontainer README.md is generated with project information', function () {
    mkdir('.seaman');
    file_put_contents(
        '.seaman/seaman.yaml',
        <<<YAML
version: "1.0"
project_type: "existing"
php:
  version: "8.4"
  xdebug:
    enabled: false
    ide_key: "VSCODE"
    client_host: "host.docker.internal"
services: {}
volumes:
  persist: []
YAML,
    );
    file_put_contents('docker-compose.yml', 'services: {}');

    $app = new Application();
    $command = $app->find('devcontainer:generate');
    $tester = new CommandTester($command);
    $tester->setInputs(['']);
    $tester->execute([]);

    $readme = file_get_contents('.devcontainer/README.md');

    expect($readme)->toContain('# DevContainer Configuration')
        ->and($readme)->toContain('What are DevContainers?')
        ->and($readme)->toContain('How to Use');
});

test('devcontainer xdebug settings reflect seaman.yaml configuration', function () {
    mkdir('.seaman');
    file_put_contents(
        '.seaman/seaman.yaml',
        <<<YAML
version: "1.0"
project_type: "existing"
php:
  version: "8.4"
  xdebug:
    enabled: true
    ide_key: "CUSTOM_KEY"
    client_host: "custom.host"
services: {}
volumes:
  persist: []
YAML,
    );

    file_put_contents('docker-compose.yml', 'services: {}');

    $app = new Application();
    $command = $app->find('devcontainer:generate');
    $tester = new CommandTester($command);
    $tester->setInputs(['']);
    $tester->execute([]);

    $json = file_get_contents('.devcontainer/devcontainer.json');
    $decoded = json_decode($json, true);

    $settings = $decoded['customizations']['vscode']['settings'] ?? [];

    expect($settings['xdebug.mode'])->toBe('debug')
        ->and($settings['php.debug.ideKey'])->toBe('CUSTOM_KEY')
        ->and($settings['php.debug.host'])->toBe('custom.host');
});
