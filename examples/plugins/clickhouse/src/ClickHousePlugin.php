<?php

declare(strict_types=1);

// ABOUTME: ClickHouse plugin for Seaman - demonstrates all plugin extension points.
// ABOUTME: Provides ClickHouse OLAP database service with custom commands and lifecycle hooks.

namespace Seaman\Plugin\ClickHouse;

use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\OnLifecycle;
use Seaman\Plugin\Attribute\ProvidesCommand;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\LifecycleEvent;
use Seaman\Plugin\LifecycleEventData;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;
use Seaman\Plugin\ClickHouse\Command\ClickHouseQueryCommand;
use Seaman\ValueObject\HealthCheck;
use Symfony\Component\Console\Command\Command;

#[AsSeamanPlugin(
    name: 'seaman/clickhouse-plugin',
    version: '1.0.0',
    description: 'ClickHouse OLAP database service for Seaman',
)]
final class ClickHousePlugin implements PluginInterface
{
    private ConfigSchema $schema;

    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct()
    {
        $this->schema = ConfigSchema::create()
            ->string('version', default: '24.8')
            ->string('user', default: 'default')
            ->string('password', default: '', nullable: true)
            ->string('database', default: 'default')
            ->integer('http_port', default: 8123, min: 1, max: 65535)
            ->integer('native_port', default: 9000, min: 1, max: 65535)
            ->boolean('enable_backups', default: true);

        // Initialize with defaults
        $this->config = $this->schema->validate([]);
    }

    public function getName(): string
    {
        return 'seaman/clickhouse-plugin';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'ClickHouse OLAP database service for Seaman';
    }

    /**
     * Return the configuration schema for validation.
     */
    public function configSchema(): ConfigSchema
    {
        return $this->schema;
    }

    /**
     * Configure the plugin with user values.
     *
     * @param array<string, mixed> $values
     */
    public function configure(array $values): void
    {
        $this->config = $this->schema->validate($values);
    }

    /**
     * Provide the ClickHouse Docker service.
     */
    #[ProvidesService(name: 'clickhouse', category: ServiceCategory::Database)]
    public function clickhouseService(): ServiceDefinition
    {
        return new ServiceDefinition(
            name: 'clickhouse',
            template: __DIR__ . '/../templates/clickhouse.yaml.twig',
            displayName: 'ClickHouse',
            description: 'Fast open-source column-oriented OLAP database',
            icon: '🏠',
            category: ServiceCategory::Database,
            ports: [
                (int) $this->config['http_port'],
                (int) $this->config['native_port'],
            ],
            internalPorts: [9009],
            defaultConfig: [
                'version' => $this->config['version'],
                'user' => $this->config['user'],
                'password' => $this->config['password'],
                'database' => $this->config['database'],
                'http_port' => $this->config['http_port'],
                'native_port' => $this->config['native_port'],
            ],
            healthCheck: new HealthCheck(
                test: ['CMD', 'wget', '--no-verbose', '--tries=1', '--spider', 'http://localhost:8123/ping'],
                interval: '10s',
                timeout: '5s',
                retries: 3,
            ),
        );
    }

    /**
     * Provide a command to execute ClickHouse queries.
     */
    #[ProvidesCommand]
    public function queryCommand(): Command
    {
        return new ClickHouseQueryCommand();
    }

    /**
     * Log a message after containers start.
     */
    #[OnLifecycle(event: LifecycleEvent::AfterStart->value, priority: 10)]
    public function onAfterStart(LifecycleEventData $data): void
    {
        $httpPort = $this->config['http_port'];
        $nativePort = $this->config['native_port'];

        echo "\n";
        echo "  ClickHouse is ready!\n";
        echo "  ├── HTTP API: http://localhost:{$httpPort}\n";
        echo "  ├── Native:   localhost:{$nativePort}\n";
        echo "  └── Query:    seaman clickhouse:query \"SELECT version()\"\n";
        echo "\n";
    }

    /**
     * Warn about data before destroying containers.
     */
    #[OnLifecycle(event: LifecycleEvent::BeforeDestroy->value, priority: 100)]
    public function onBeforeDestroy(LifecycleEventData $data): void
    {
        if ($this->config['enable_backups']) {
            echo "\n";
            echo "  ⚠️  ClickHouse data will be deleted!\n";
            echo "  Consider running: seaman clickhouse:query \"SELECT * FROM system.tables\" > backup.sql\n";
            echo "\n";
        }
    }
}
