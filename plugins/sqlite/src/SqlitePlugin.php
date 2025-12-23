<?php

declare(strict_types=1);

// ABOUTME: SQLite bundled plugin for Seaman.
// ABOUTME: Provides SQLite file-based database configuration (no Docker container needed).

namespace Seaman\Plugin\Sqlite;

use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;

#[AsSeamanPlugin(
    name: 'seaman/sqlite',
    version: '1.0.0',
    description: 'SQLite file-based database for Seaman',
)]
final class SqlitePlugin implements PluginInterface
{
    private ConfigSchema $schema;

    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct()
    {
        $this->schema = ConfigSchema::create()
            ->string('version', default: '3')
            ->string('database_path', default: 'var/data.db');

        $this->config = $this->schema->validate([]);
    }

    public function getName(): string
    {
        return 'seaman/sqlite';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'SQLite file-based database for Seaman';
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

    #[ProvidesService(name: 'sqlite', category: ServiceCategory::Database)]
    public function sqliteService(): ServiceDefinition
    {
        return new ServiceDefinition(
            name: 'sqlite',
            template: __DIR__ . '/../templates/sqlite.yaml.twig',
            displayName: 'SQLite',
            description: 'Lightweight file-based SQL database',
            icon: '📄',
            category: ServiceCategory::Database,
            ports: [],
            internalPorts: [],
            defaultConfig: [
                'version' => $this->config['version'],
                'database_path' => $this->config['database_path'],
            ],
            healthCheck: null,
        );
    }
}
