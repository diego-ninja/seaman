<?php

declare(strict_types=1);

// ABOUTME: Integration tests for InitCommand.
// ABOUTME: Validates interactive initialization flow.

namespace Tests\Integration\Command;

use Symfony\Component\Console\Tester\CommandTester;
use Seaman\Command\InitCommand;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\Service\Container\PostgresqlService;
use Seaman\Service\Container\MysqlService;
use Seaman\Service\Container\MariadbService;
use Seaman\Service\Container\RedisService;
use Seaman\Service\Container\MailpitService;
use Seaman\Service\Container\MinioService;
use Seaman\Service\Container\ElasticsearchService;
use Seaman\Service\Container\RabbitmqService;

/**
 * @property string $testDir
 */
beforeEach(function (): void {
    $this->testDir = sys_get_temp_dir() . '/seaman-init-test-' . uniqid();
    mkdir($this->testDir, 0755, true);
    chdir($this->testDir);

    // Copy root Dockerfile to test directory
    $rootDockerfile = __DIR__ . '/../../../Dockerfile';
    if (file_exists($rootDockerfile)) {
        copy($rootDockerfile, $this->testDir . '/Dockerfile');
    }
});

afterEach(function (): void {
    if (is_dir($this->testDir)) {
        exec("rm -rf {$this->testDir}");
    }
});

test('init command creates seaman.yaml', function (): void {
    $registry = new ServiceRegistry();
    $registry->register(new PostgresqlService());
    $registry->register(new MysqlService());
    $registry->register(new MariadbService());
    $registry->register(new RedisService());
    $registry->register(new MailpitService());
    $registry->register(new MinioService());
    $registry->register(new ElasticsearchService());
    $registry->register(new RabbitmqService());

    $command = new InitCommand($registry);
    $tester = new CommandTester($command);

    $tester->setInputs([
        '8.4',           // PHP version
        'postgresql',    // Database
        'redis,mailpit', // Additional services
    ]);

    $tester->execute([]);

    expect(file_exists($this->testDir . '/seaman.yaml'))->toBeTrue()
        ->and(file_exists($this->testDir . '/.seaman/Dockerfile'))->toBeTrue()
        ->and(file_exists($this->testDir . '/docker-compose.yml'))->toBeTrue();

    $output = $tester->getDisplay();
    expect($output)->toContain('Seaman initialized successfully!');
});
