<?php

declare(strict_types=1);

// ABOUTME: Tests for DatabaseCommandBuilder service.
// ABOUTME: Validates command generation for dump, restore, and shell operations.

namespace Seaman\Tests\Unit\Service;

use Seaman\Enum\Service;
use Seaman\Service\DatabaseCommandBuilder;
use Seaman\ValueObject\ServiceConfig;

test('builds dump command for PostgreSQL', function (): void {
    $builder = new DatabaseCommandBuilder();
    $config = new ServiceConfig(
        name: 'postgresql',
        enabled: true,
        type: Service::PostgreSQL,
        version: '16',
        port: 5432,
        additionalPorts: [],
        environmentVariables: [
            'POSTGRES_USER' => 'myuser',
            'POSTGRES_DB' => 'mydb',
        ],
    );

    $command = $builder->dump($config);

    expect($command)->toBe(['pg_dump', '-U', 'myuser', 'mydb']);
});

test('builds dump command for MySQL', function (): void {
    $builder = new DatabaseCommandBuilder();
    $config = new ServiceConfig(
        name: 'mysql',
        enabled: true,
        type: Service::MySQL,
        version: '8.0',
        port: 3306,
        additionalPorts: [],
        environmentVariables: [
            'MYSQL_USER' => 'myuser',
            'MYSQL_PASSWORD' => 'secret',
            'MYSQL_DATABASE' => 'mydb',
        ],
    );

    $command = $builder->dump($config);

    expect($command)->toBe(['mysqldump', '-u', 'myuser', '-psecret', 'mydb']);
});

test('builds dump command for MariaDB', function (): void {
    $builder = new DatabaseCommandBuilder();
    $config = new ServiceConfig(
        name: 'mariadb',
        enabled: true,
        type: Service::MariaDB,
        version: '10.11',
        port: 3306,
        additionalPorts: [],
        environmentVariables: [
            'MYSQL_USER' => 'root',
            'MYSQL_PASSWORD' => 'pass',
            'MYSQL_DATABASE' => 'app',
        ],
    );

    $command = $builder->dump($config);

    expect($command)->toBe(['mysqldump', '-u', 'root', '-ppass', 'app']);
});

test('builds dump command for SQLite', function (): void {
    $builder = new DatabaseCommandBuilder();
    $config = new ServiceConfig(
        name: 'sqlite',
        enabled: true,
        type: Service::SQLite,
        version: '3',
        port: 0,
        additionalPorts: [],
        environmentVariables: [
            'SQLITE_DATABASE' => '/data/app.db',
        ],
    );

    $command = $builder->dump($config);

    expect($command)->toBe(['sqlite3', '/data/app.db', '.dump']);
});

test('builds dump command for MongoDB', function (): void {
    $builder = new DatabaseCommandBuilder();
    $config = new ServiceConfig(
        name: 'mongodb',
        enabled: true,
        type: Service::MongoDB,
        version: '7',
        port: 27017,
        additionalPorts: [],
        environmentVariables: [
            'MONGO_INITDB_ROOT_USERNAME' => 'admin',
            'MONGO_INITDB_ROOT_PASSWORD' => 'secret',
        ],
    );

    $command = $builder->dump($config);

    expect($command)->toBe([
        'mongodump',
        '--username', 'admin',
        '--password', 'secret',
        '--authenticationDatabase', 'admin',
        '--archive',
    ]);
});

test('builds restore command for PostgreSQL', function (): void {
    $builder = new DatabaseCommandBuilder();
    $config = new ServiceConfig(
        name: 'postgresql',
        enabled: true,
        type: Service::PostgreSQL,
        version: '16',
        port: 5432,
        additionalPorts: [],
        environmentVariables: [
            'POSTGRES_USER' => 'myuser',
            'POSTGRES_DB' => 'mydb',
        ],
    );

    $command = $builder->restore($config);

    expect($command)->toBe(['psql', '-U', 'myuser', 'mydb']);
});

test('builds restore command for MySQL', function (): void {
    $builder = new DatabaseCommandBuilder();
    $config = new ServiceConfig(
        name: 'mysql',
        enabled: true,
        type: Service::MySQL,
        version: '8.0',
        port: 3306,
        additionalPorts: [],
        environmentVariables: [
            'MYSQL_USER' => 'myuser',
            'MYSQL_PASSWORD' => 'secret',
            'MYSQL_DATABASE' => 'mydb',
        ],
    );

    $command = $builder->restore($config);

    expect($command)->toBe(['mysql', '-u', 'myuser', '-psecret', 'mydb']);
});

test('builds restore command for MongoDB', function (): void {
    $builder = new DatabaseCommandBuilder();
    $config = new ServiceConfig(
        name: 'mongodb',
        enabled: true,
        type: Service::MongoDB,
        version: '7',
        port: 27017,
        additionalPorts: [],
        environmentVariables: [
            'MONGO_INITDB_ROOT_USERNAME' => 'admin',
            'MONGO_INITDB_ROOT_PASSWORD' => 'secret',
        ],
    );

    $command = $builder->restore($config);

    expect($command)->toBe([
        'mongorestore',
        '--username', 'admin',
        '--password', 'secret',
        '--authenticationDatabase', 'admin',
        '--archive',
        '--drop',
    ]);
});

test('builds shell command for PostgreSQL', function (): void {
    $builder = new DatabaseCommandBuilder();
    $config = new ServiceConfig(
        name: 'postgresql',
        enabled: true,
        type: Service::PostgreSQL,
        version: '16',
        port: 5432,
        additionalPorts: [],
        environmentVariables: [
            'POSTGRES_USER' => 'myuser',
            'POSTGRES_DB' => 'mydb',
        ],
    );

    $command = $builder->shell($config);

    expect($command)->toBe(['psql', '-U', 'myuser', 'mydb']);
});

test('builds shell command for MySQL', function (): void {
    $builder = new DatabaseCommandBuilder();
    $config = new ServiceConfig(
        name: 'mysql',
        enabled: true,
        type: Service::MySQL,
        version: '8.0',
        port: 3306,
        additionalPorts: [],
        environmentVariables: [
            'MYSQL_USER' => 'myuser',
            'MYSQL_PASSWORD' => 'secret',
            'MYSQL_DATABASE' => 'mydb',
        ],
    );

    $command = $builder->shell($config);

    expect($command)->toBe(['mysql', '-u', 'myuser', '-psecret', 'mydb']);
});

test('builds shell command for MongoDB', function (): void {
    $builder = new DatabaseCommandBuilder();
    $config = new ServiceConfig(
        name: 'mongodb',
        enabled: true,
        type: Service::MongoDB,
        version: '7',
        port: 27017,
        additionalPorts: [],
        environmentVariables: [
            'MONGO_INITDB_ROOT_USERNAME' => 'admin',
            'MONGO_INITDB_ROOT_PASSWORD' => 'secret',
        ],
    );

    $command = $builder->shell($config);

    expect($command)->toBe([
        'mongosh',
        '--username', 'admin',
        '--password', 'secret',
        '--authenticationDatabase', 'admin',
    ]);
});

test('uses default values when environment variables are missing', function (): void {
    $builder = new DatabaseCommandBuilder();
    $config = new ServiceConfig(
        name: 'postgresql',
        enabled: true,
        type: Service::PostgreSQL,
        version: '16',
        port: 5432,
        additionalPorts: [],
        environmentVariables: [],
    );

    $command = $builder->dump($config);

    expect($command)->toBe(['pg_dump', '-U', 'postgres', 'postgres']);
});

test('returns null for unsupported database type', function (): void {
    $builder = new DatabaseCommandBuilder();
    $config = new ServiceConfig(
        name: 'redis',
        enabled: true,
        type: Service::Redis,
        version: '7',
        port: 6379,
        additionalPorts: [],
        environmentVariables: [],
    );

    expect($builder->dump($config))->toBeNull()
        ->and($builder->restore($config))->toBeNull()
        ->and($builder->shell($config))->toBeNull();
});
