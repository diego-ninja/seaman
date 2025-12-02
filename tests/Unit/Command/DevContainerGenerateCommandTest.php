<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Command;

use Seaman\Command\DevContainerGenerateCommand;
use Seaman\Exception\SeamanException;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\Service\DevContainerGenerator;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    $this->testDir = sys_get_temp_dir() . '/seaman-test-' . uniqid();
    mkdir($this->testDir);
    chdir($this->testDir);
});

afterEach(function () {
    if (is_dir($this->testDir)) {
        exec("rm -rf {$this->testDir}");
    }
});

test('command requires seaman.yaml to exist', function () {
    $registry = new ServiceRegistry();
    $generator = null;

    $command = new DevContainerGenerateCommand($registry, $generator);
    $tester = new CommandTester($command);

    expect(fn() => $tester->execute([]))->toThrow(SeamanException::class, 'seaman.yaml not found');
});

test('command requires docker-compose.yml to exist', function () {
    // Create seaman.yaml but not docker-compose.yml
    mkdir('.seaman');
    file_put_contents('.seaman/seaman.yaml', 'version: "1.0"');

    $registry = new ServiceRegistry();
    $generator = null;

    $command = new DevContainerGenerateCommand($registry, $generator);
    $tester = new CommandTester($command);

    expect(fn() => $tester->execute([]))->toThrow(SeamanException::class, 'docker-compose.yml not found');
});

test('command generates devcontainer files successfully', function () {
    // Create seaman.yaml and docker-compose.yml
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
    file_put_contents('docker-compose.yml', 'services:');

    $registry = new ServiceRegistry();
    $generator = null;

    $command = new DevContainerGenerateCommand($registry, $generator);
    $tester = new CommandTester($command);

    $tester->setInputs(['']); // Answer any prompts with default
    $tester->execute([]);

    expect($tester->getStatusCode())->toBe(0)
        ->and(file_exists('.devcontainer/devcontainer.json'))->toBeTrue()
        ->and(file_exists('.devcontainer/README.md'))->toBeTrue();
});
