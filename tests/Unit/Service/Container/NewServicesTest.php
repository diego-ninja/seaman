<?php

declare(strict_types=1);

// ABOUTME: Tests for Kafka and SQLite service implementations.
// ABOUTME: Validates Kafka and SQLite services configuration and behavior.

namespace Seaman\Tests\Unit\Service\Container;

use Seaman\Enum\Service;
use Seaman\Service\Container\KafkaService;
use Seaman\Service\Container\SqliteService;
use Seaman\Service\Container\ServiceInterface;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

test('KafkaService implements ServiceInterface', function () {
    $service = new KafkaService();

    expect($service)->toBeInstanceOf(ServiceInterface::class);
});

test('KafkaService returns correct name', function () {
    $service = new KafkaService();

    expect($service->getName())->toBe('kafka');
});

test('KafkaService returns correct display name', function () {
    $service = new KafkaService();

    expect($service->getDisplayName())->toBe('Kafka');
});

test('KafkaService returns correct description', function () {
    $service = new KafkaService();

    expect($service->getDescription())->toBe('Apache Kafka distributed event streaming platform');
});

test('KafkaService has no dependencies', function () {
    $service = new KafkaService();

    expect($service->getDependencies())->toBe([]);
});

test('KafkaService returns default config with correct values', function () {
    $service = new KafkaService();
    $config = $service->getDefaultConfig();

    expect($config)->toBeInstanceOf(ServiceConfig::class)
        ->and($config->name)->toBe('kafka')
        ->and($config->enabled)->toBe(false)
        ->and($config->type)->toBe(Service::Kafka)
        ->and($config->version)->toBe('latest')
        ->and($config->port)->toBe(9092)
        ->and($config->additionalPorts)->toBe([])
        ->and($config->environmentVariables)->toHaveKey('KAFKA_NODE_ID')
        ->and($config->environmentVariables)->toHaveKey('KAFKA_PROCESS_ROLES')
        ->and($config->environmentVariables)->toHaveKey('KAFKA_LISTENERS')
        ->and($config->environmentVariables)->toHaveKey('KAFKA_LISTENER_SECURITY_PROTOCOL_MAP')
        ->and($config->environmentVariables)->toHaveKey('KAFKA_CONTROLLER_QUORUM_VOTERS')
        ->and($config->environmentVariables)->toHaveKey('KAFKA_CONTROLLER_LISTENER_NAMES');
});

test('KafkaService returns required ports', function () {
    $service = new KafkaService();

    expect($service->getRequiredPorts())->toBe([9092]);
});

test('KafkaService returns health check configuration', function () {
    $service = new KafkaService();
    $healthCheck = $service->getHealthCheck();

    expect($healthCheck)->toBeInstanceOf(HealthCheck::class);

    if ($healthCheck !== null) {
        expect($healthCheck->test)->toContain('CMD-SHELL')
            ->and($healthCheck->interval)->toBe('10s')
            ->and($healthCheck->timeout)->toBe('10s')
            ->and($healthCheck->retries)->toBe(5);
    }
});

test('KafkaService generates docker compose config', function () {
    $service = new KafkaService();
    $config = new ServiceConfig(
        name: 'kafka',
        enabled: true,
        type: Service::Kafka,
        version: '3.7',
        port: 9092,
        additionalPorts: [],
        environmentVariables: [
            'KAFKA_CFG_NODE_ID' => '0',
            'KAFKA_CFG_PROCESS_ROLES' => 'controller,broker',
            'KAFKA_CFG_LISTENERS' => 'PLAINTEXT://:9092,CONTROLLER://:9093',
            'KAFKA_CFG_LISTENER_SECURITY_PROTOCOL_MAP' => 'CONTROLLER:PLAINTEXT,PLAINTEXT:PLAINTEXT',
            'KAFKA_CFG_CONTROLLER_QUORUM_VOTERS' => '0@kafka:9093',
            'KAFKA_CFG_CONTROLLER_LISTENER_NAMES' => 'CONTROLLER',
        ],
    );

    $compose = $service->generateComposeConfig($config);

    expect($compose)->toHaveKey('image')
        ->and($compose['image'])->toBe('apache/kafka:3.7')
        ->and($compose)->toHaveKey('environment')
        ->and($compose['environment'])->toHaveKey('KAFKA_CFG_NODE_ID')
        ->and($compose['environment'])->toHaveKey('KAFKA_CFG_PROCESS_ROLES')
        ->and($compose)->toHaveKey('ports')
        ->and($compose['ports'])->toContain('9092:9092')
        ->and($compose)->toHaveKey('healthcheck')
        ->and($compose)->toHaveKey('volumes');
});

test('KafkaService returns correct env variables', function () {
    $service = new KafkaService();
    $config = new ServiceConfig(
        name: 'kafka',
        enabled: true,
        type: Service::Kafka,
        version: '3.7',
        port: 9092,
        additionalPorts: [],
        environmentVariables: [],
    );

    $envVars = $service->getEnvVariables($config);

    expect($envVars)->toHaveKey('KAFKA_PORT')
        ->and($envVars)->toHaveKey('KAFKA_BROKER')
        ->and($envVars['KAFKA_PORT'])->toBe(9092)
        ->and($envVars['KAFKA_BROKER'])->toBe('localhost:9092');
});

test('SqliteService implements ServiceInterface', function () {
    $service = new SqliteService();

    expect($service)->toBeInstanceOf(ServiceInterface::class);
});

test('SqliteService returns correct name', function () {
    $service = new SqliteService();

    expect($service->getName())->toBe('sqlite');
});

test('SqliteService returns correct display name', function () {
    $service = new SqliteService();

    expect($service->getDisplayName())->toBe('SQLite');
});

test('SqliteService returns correct description', function () {
    $service = new SqliteService();

    expect($service->getDescription())->toBe('SQLite file-based relational database');
});

test('SqliteService has no dependencies', function () {
    $service = new SqliteService();

    expect($service->getDependencies())->toBe([]);
});

test('SqliteService returns default config with correct values', function () {
    $service = new SqliteService();
    $config = $service->getDefaultConfig();

    expect($config)->toBeInstanceOf(ServiceConfig::class)
        ->and($config->name)->toBe('sqlite')
        ->and($config->enabled)->toBe(false)
        ->and($config->type)->toBe(Service::SQLite)
        ->and($config->version)->toBe('3')
        ->and($config->port)->toBe(0)
        ->and($config->additionalPorts)->toBe([])
        ->and($config->environmentVariables)->toHaveKey('DATABASE_PATH');
});

test('SqliteService returns no required ports', function () {
    $service = new SqliteService();

    expect($service->getRequiredPorts())->toBe([]);
});

test('SqliteService returns null health check', function () {
    $service = new SqliteService();
    $healthCheck = $service->getHealthCheck();

    expect($healthCheck)->toBeNull();
});

test('SqliteService generates empty docker compose config', function () {
    $service = new SqliteService();
    $config = new ServiceConfig(
        name: 'sqlite',
        enabled: true,
        type: Service::SQLite,
        version: '3',
        port: 0,
        additionalPorts: [],
        environmentVariables: [
            'DATABASE_PATH' => 'var/data.db',
        ],
    );

    $compose = $service->generateComposeConfig($config);

    expect($compose)->toBe([]);
});

test('SqliteService returns correct env variables', function () {
    $service = new SqliteService();
    $config = new ServiceConfig(
        name: 'sqlite',
        enabled: true,
        type: Service::SQLite,
        version: '3',
        port: 0,
        additionalPorts: [],
        environmentVariables: [
            'DATABASE_PATH' => 'var/data.db',
        ],
    );

    $envVars = $service->getEnvVariables($config);

    expect($envVars)->toHaveKey('DATABASE_URL')
        ->and($envVars)->toHaveKey('DB_CONNECTION')
        ->and($envVars)->toHaveKey('DB_DATABASE')
        ->and($envVars['DB_CONNECTION'])->toBe('sqlite')
        ->and($envVars['DB_DATABASE'])->toBe('var/data.db')
        ->and($envVars['DATABASE_URL'])->toContain('sqlite:///%kernel.project_dir%/var/data.db');
});

test('SqliteService handles custom database path', function () {
    $service = new SqliteService();
    $config = new ServiceConfig(
        name: 'sqlite',
        enabled: true,
        type: Service::SQLite,
        version: '3',
        port: 0,
        additionalPorts: [],
        environmentVariables: [
            'DATABASE_PATH' => 'custom/path/db.sqlite',
        ],
    );

    $envVars = $service->getEnvVariables($config);

    expect($envVars['DB_DATABASE'])->toBe('custom/path/db.sqlite')
        ->and($envVars['DATABASE_URL'])->toContain('sqlite:///%kernel.project_dir%/custom/path/db.sqlite');
});
