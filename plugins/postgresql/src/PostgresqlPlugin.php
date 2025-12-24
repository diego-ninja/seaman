<?php

declare(strict_types=1);

// ABOUTME: PostgreSQL bundled plugin for Seaman.
// ABOUTME: Provides PostgreSQL database service with health checks and data persistence.

namespace Seaman\Plugin\Postgresql;

use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\DatabaseOperations;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;
use Seaman\ValueObject\HealthCheck;

#[AsSeamanPlugin(
    name: 'seaman/postgresql',
    version: '1.0.0',
    description: 'PostgreSQL database service for Seaman',
)]
final class PostgresqlPlugin implements PluginInterface
{
    private ConfigSchema $schema;

    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct()
    {
        $this->schema = ConfigSchema::create()
            ->string('version', default: '16')
                ->label('PostgreSQL version')
                ->description('PostgreSQL version to use')
                ->enum(['13', '14', '15', '16', '17', 'latest'])
            ->integer('port', default: 5432, min: 1, max: 65535)
                ->label('Port')
                ->description('Port number for PostgreSQL')
            ->string('database', default: 'seaman')
                ->label('Database name')
                ->description('Name of the database to create')
            ->string('user', default: 'seaman')
                ->label('Database user')
                ->description('Username for database access')
            ->string('password', default: 'seaman')
                ->label('Database password')
                ->description('Password for database user')
                ->secret();

        $this->config = $this->schema->validate([]);
    }

    public function getName(): string
    {
        return 'seaman/postgresql';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'PostgreSQL database service for Seaman';
    }

    public function configSchema(): ConfigSchema
    {
        return $this->schema;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function configure(array $values): void
    {
        $this->config = $this->schema->validate($values);
    }

    #[ProvidesService(name: 'postgresql', category: ServiceCategory::Database)]
    public function postgresqlService(): ServiceDefinition
    {
        $port = $this->config['port'];
        assert(is_int($port));

        return new ServiceDefinition(
            name: 'postgresql',
            template: __DIR__ . '/../templates/postgresql.yaml.twig',
            displayName: 'PostgreSQL',
            description: 'Advanced open-source relational database',
            icon: '🐘',
            category: ServiceCategory::Database,
            ports: [$port],
            internalPorts: [5432],
            defaultConfig: [
                'version' => $this->config['version'],
                'port' => $this->config['port'],
                'database' => $this->config['database'],
                'user' => $this->config['user'],
                'password' => $this->config['password'],
                'environment' => [
                    'POSTGRES_DB' => $this->config['database'],
                    'POSTGRES_USER' => $this->config['user'],
                    'POSTGRES_PASSWORD' => $this->config['password'],
                ],
            ],
            healthCheck: new HealthCheck(
                test: ['CMD-SHELL', 'pg_isready -U $POSTGRES_USER'],
                interval: '10s',
                timeout: '5s',
                retries: 5,
            ),
            databaseOperations: new DatabaseOperations(
                dumpCommand: static fn($config) => [
                    'pg_dump',
                    '-U',
                    $config->environmentVariables['POSTGRES_USER'] ?? 'postgres',
                    $config->environmentVariables['POSTGRES_DB'] ?? 'postgres',
                ],
                restoreCommand: static fn($config) => [
                    'psql',
                    '-U',
                    $config->environmentVariables['POSTGRES_USER'] ?? 'postgres',
                    $config->environmentVariables['POSTGRES_DB'] ?? 'postgres',
                ],
                shellCommand: static fn($config) => [
                    'psql',
                    '-U',
                    $config->environmentVariables['POSTGRES_USER'] ?? 'postgres',
                    $config->environmentVariables['POSTGRES_DB'] ?? 'postgres',
                ],
            ),
            configSchema: $this->schema,
        );
    }
}
