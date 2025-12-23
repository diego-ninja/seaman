<?php

declare(strict_types=1);

// ABOUTME: MariaDB bundled plugin for Seaman.
// ABOUTME: Provides MariaDB database service with health checks and data persistence.

namespace Seaman\Plugin\Mariadb;

use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;
use Seaman\ValueObject\HealthCheck;

#[AsSeamanPlugin(
    name: 'seaman/mariadb',
    version: '1.0.0',
    description: 'MariaDB database service for Seaman',
)]
final class MariadbPlugin implements PluginInterface
{
    private ConfigSchema $schema;

    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct()
    {
        $this->schema = ConfigSchema::create()
            ->string('version', default: '11')
            ->integer('port', default: 3306, min: 1, max: 65535)
            ->string('database', default: 'seaman')
            ->string('user', default: 'seaman')
            ->string('password', default: 'seaman')
            ->string('root_password', default: 'root');

        $this->config = $this->schema->validate([]);
    }

    public function getName(): string
    {
        return 'seaman/mariadb';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'MariaDB database service for Seaman';
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

    #[ProvidesService(name: 'mariadb', category: ServiceCategory::Database)]
    public function mariadbService(): ServiceDefinition
    {
        $port = $this->config['port'];
        assert(is_int($port));

        return new ServiceDefinition(
            name: 'mariadb',
            template: __DIR__ . '/../templates/mariadb.yaml.twig',
            displayName: 'MariaDB',
            description: 'Community-developed MySQL fork',
            icon: '🦭',
            category: ServiceCategory::Database,
            ports: [$port],
            internalPorts: [3306],
            defaultConfig: [
                'version' => $this->config['version'],
                'port' => $this->config['port'],
                'database' => $this->config['database'],
                'user' => $this->config['user'],
                'password' => $this->config['password'],
                'root_password' => $this->config['root_password'],
            ],
            healthCheck: new HealthCheck(
                test: ['CMD-SHELL', 'healthcheck.sh --connect --innodb_initialized'],
                interval: '10s',
                timeout: '5s',
                retries: 5,
            ),
        );
    }
}
