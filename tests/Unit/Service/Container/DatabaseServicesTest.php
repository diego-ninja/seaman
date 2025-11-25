<?php

declare(strict_types=1);

// ABOUTME: Tests for database service implementations.
// ABOUTME: Validates PostgreSQL, MySQL, and MariaDB services.

namespace Seaman\Tests\Unit\Service\Container;

use Seaman\Service\Container\PostgresqlService;
use Seaman\Service\Container\MysqlService;
use Seaman\Service\Container\MariadbService;
use Seaman\Service\Container\ServiceInterface;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

test('PostgresqlService implements ServiceInterface', function () {
    $service = new PostgresqlService();

    expect($service)->toBeInstanceOf(ServiceInterface::class);
});

test('PostgresqlService returns correct name', function () {
    $service = new PostgresqlService();

    expect($service->getName())->toBe('postgresql');
});

test('PostgresqlService returns correct display name', function () {
    $service = new PostgresqlService();

    expect($service->getDisplayName())->toBe('PostgreSQL');
});

test('PostgresqlService returns correct description', function () {
    $service = new PostgresqlService();

    expect($service->getDescription())->toBe('PostgreSQL relational database');
});

test('PostgresqlService has no dependencies', function () {
    $service = new PostgresqlService();

    expect($service->getDependencies())->toBe([]);
});

test('PostgresqlService returns default config with correct values', function () {
    $service = new PostgresqlService();
    $config = $service->getDefaultConfig();

    expect($config)->toBeInstanceOf(ServiceConfig::class)
        ->and($config->name)->toBe('postgresql')
        ->and($config->enabled)->toBe(false)
        ->and($config->type)->toBe('postgresql')
        ->and($config->version)->toBe('16')
        ->and($config->port)->toBe(5432)
        ->and($config->additionalPorts)->toBe([])
        ->and($config->environmentVariables)->toHaveKey('POSTGRES_DB')
        ->and($config->environmentVariables)->toHaveKey('POSTGRES_USER')
        ->and($config->environmentVariables)->toHaveKey('POSTGRES_PASSWORD');
});

test('PostgresqlService returns required ports', function () {
    $service = new PostgresqlService();

    expect($service->getRequiredPorts())->toBe([5432]);
});

test('PostgresqlService returns health check configuration', function () {
    $service = new PostgresqlService();
    $healthCheck = $service->getHealthCheck();

    expect($healthCheck)->toBeInstanceOf(HealthCheck::class);

    if ($healthCheck !== null) {
        expect($healthCheck->test)->toContain('CMD-SHELL')
            ->and($healthCheck->test[1])->toContain('pg_isready')
            ->and($healthCheck->interval)->toBe('10s')
            ->and($healthCheck->timeout)->toBe('5s')
            ->and($healthCheck->retries)->toBe(5);
    }
});

test('PostgresqlService generates docker compose config', function () {
    $service = new PostgresqlService();
    $config = new ServiceConfig(
        name: 'postgresql',
        enabled: true,
        type: 'postgresql',
        version: '16',
        port: 5432,
        additionalPorts: [],
        environmentVariables: [
            'POSTGRES_DB' => 'testdb',
            'POSTGRES_USER' => 'testuser',
            'POSTGRES_PASSWORD' => 'testpass',
        ],
    );

    $compose = $service->generateComposeConfig($config);

    expect($compose)->toHaveKey('image')
        ->and($compose['image'])->toBe('postgres:16')
        ->and($compose)->toHaveKey('environment')
        ->and($compose['environment'])->toHaveKey('POSTGRES_DB')
        ->and($compose['environment'])->toHaveKey('POSTGRES_USER')
        ->and($compose['environment'])->toHaveKey('POSTGRES_PASSWORD')
        ->and($compose)->toHaveKey('ports')
        ->and($compose['ports'])->toContain('5432:5432')
        ->and($compose)->toHaveKey('healthcheck')
        ->and($compose)->toHaveKey('volumes');
});

test('MysqlService implements ServiceInterface', function () {
    $service = new MysqlService();

    expect($service)->toBeInstanceOf(ServiceInterface::class);
});

test('MysqlService returns correct name', function () {
    $service = new MysqlService();

    expect($service->getName())->toBe('mysql');
});

test('MysqlService returns correct display name', function () {
    $service = new MysqlService();

    expect($service->getDisplayName())->toBe('MySQL');
});

test('MysqlService returns correct description', function () {
    $service = new MysqlService();

    expect($service->getDescription())->toBe('MySQL relational database');
});

test('MysqlService has no dependencies', function () {
    $service = new MysqlService();

    expect($service->getDependencies())->toBe([]);
});

test('MysqlService returns default config with correct values', function () {
    $service = new MysqlService();
    $config = $service->getDefaultConfig();

    expect($config)->toBeInstanceOf(ServiceConfig::class)
        ->and($config->name)->toBe('mysql')
        ->and($config->enabled)->toBe(false)
        ->and($config->type)->toBe('mysql')
        ->and($config->version)->toBe('8.0')
        ->and($config->port)->toBe(3306)
        ->and($config->additionalPorts)->toBe([])
        ->and($config->environmentVariables)->toHaveKey('MYSQL_DATABASE')
        ->and($config->environmentVariables)->toHaveKey('MYSQL_USER')
        ->and($config->environmentVariables)->toHaveKey('MYSQL_PASSWORD')
        ->and($config->environmentVariables)->toHaveKey('MYSQL_ROOT_PASSWORD');
});

test('MysqlService returns required ports', function () {
    $service = new MysqlService();

    expect($service->getRequiredPorts())->toBe([3306]);
});

test('MysqlService returns health check configuration', function () {
    $service = new MysqlService();
    $healthCheck = $service->getHealthCheck();

    expect($healthCheck)->toBeInstanceOf(HealthCheck::class);

    if ($healthCheck !== null) {
        expect($healthCheck->test)->toContain('CMD')
            ->and($healthCheck->test)->toContain('mysqladmin')
            ->and($healthCheck->test)->toContain('ping')
            ->and($healthCheck->interval)->toBe('10s')
            ->and($healthCheck->timeout)->toBe('5s')
            ->and($healthCheck->retries)->toBe(5);
    }
});

test('MysqlService generates docker compose config', function () {
    $service = new MysqlService();
    $config = new ServiceConfig(
        name: 'mysql',
        enabled: true,
        type: 'mysql',
        version: '8.0',
        port: 3306,
        additionalPorts: [],
        environmentVariables: [
            'MYSQL_DATABASE' => 'testdb',
            'MYSQL_USER' => 'testuser',
            'MYSQL_PASSWORD' => 'testpass',
            'MYSQL_ROOT_PASSWORD' => 'rootpass',
        ],
    );

    $compose = $service->generateComposeConfig($config);

    expect($compose)->toHaveKey('image')
        ->and($compose['image'])->toBe('mysql:8.0')
        ->and($compose)->toHaveKey('environment')
        ->and($compose['environment'])->toHaveKey('MYSQL_DATABASE')
        ->and($compose['environment'])->toHaveKey('MYSQL_USER')
        ->and($compose['environment'])->toHaveKey('MYSQL_PASSWORD')
        ->and($compose['environment'])->toHaveKey('MYSQL_ROOT_PASSWORD')
        ->and($compose)->toHaveKey('ports')
        ->and($compose['ports'])->toContain('3306:3306')
        ->and($compose)->toHaveKey('healthcheck')
        ->and($compose)->toHaveKey('volumes');
});

test('MariadbService implements ServiceInterface', function () {
    $service = new MariadbService();

    expect($service)->toBeInstanceOf(ServiceInterface::class);
});

test('MariadbService returns correct name', function () {
    $service = new MariadbService();

    expect($service->getName())->toBe('mariadb');
});

test('MariadbService returns correct display name', function () {
    $service = new MariadbService();

    expect($service->getDisplayName())->toBe('MariaDB');
});

test('MariadbService returns correct description', function () {
    $service = new MariadbService();

    expect($service->getDescription())->toBe('MariaDB relational database');
});

test('MariadbService has no dependencies', function () {
    $service = new MariadbService();

    expect($service->getDependencies())->toBe([]);
});

test('MariadbService returns default config with correct values', function () {
    $service = new MariadbService();
    $config = $service->getDefaultConfig();

    expect($config)->toBeInstanceOf(ServiceConfig::class)
        ->and($config->name)->toBe('mariadb')
        ->and($config->enabled)->toBe(false)
        ->and($config->type)->toBe('mariadb')
        ->and($config->version)->toBe('11')
        ->and($config->port)->toBe(3306)
        ->and($config->additionalPorts)->toBe([])
        ->and($config->environmentVariables)->toHaveKey('MARIADB_DATABASE')
        ->and($config->environmentVariables)->toHaveKey('MARIADB_USER')
        ->and($config->environmentVariables)->toHaveKey('MARIADB_PASSWORD')
        ->and($config->environmentVariables)->toHaveKey('MARIADB_ROOT_PASSWORD');
});

test('MariadbService returns required ports', function () {
    $service = new MariadbService();

    expect($service->getRequiredPorts())->toBe([3306]);
});

test('MariadbService returns health check configuration', function () {
    $service = new MariadbService();
    $healthCheck = $service->getHealthCheck();

    expect($healthCheck)->toBeInstanceOf(HealthCheck::class);

    if ($healthCheck !== null) {
        expect($healthCheck->test)->toContain('CMD-SHELL')
            ->and($healthCheck->test[1])->toContain('healthcheck.sh')
            ->and($healthCheck->interval)->toBe('10s')
            ->and($healthCheck->timeout)->toBe('5s')
            ->and($healthCheck->retries)->toBe(5);
    }
});

test('MariadbService generates docker compose config', function () {
    $service = new MariadbService();
    $config = new ServiceConfig(
        name: 'mariadb',
        enabled: true,
        type: 'mariadb',
        version: '11',
        port: 3306,
        additionalPorts: [],
        environmentVariables: [
            'MARIADB_DATABASE' => 'testdb',
            'MARIADB_USER' => 'testuser',
            'MARIADB_PASSWORD' => 'testpass',
            'MARIADB_ROOT_PASSWORD' => 'rootpass',
        ],
    );

    $compose = $service->generateComposeConfig($config);

    expect($compose)->toHaveKey('image')
        ->and($compose['image'])->toBe('mariadb:11')
        ->and($compose)->toHaveKey('environment')
        ->and($compose['environment'])->toHaveKey('MARIADB_DATABASE')
        ->and($compose['environment'])->toHaveKey('MARIADB_USER')
        ->and($compose['environment'])->toHaveKey('MARIADB_PASSWORD')
        ->and($compose['environment'])->toHaveKey('MARIADB_ROOT_PASSWORD')
        ->and($compose)->toHaveKey('ports')
        ->and($compose['ports'])->toContain('3306:3306')
        ->and($compose)->toHaveKey('healthcheck')
        ->and($compose)->toHaveKey('volumes');
});
