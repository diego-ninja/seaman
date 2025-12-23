<?php

declare(strict_types=1);

// ABOUTME: MongoDB bundled plugin for Seaman.
// ABOUTME: Provides MongoDB NoSQL database service with health checks and data persistence.

namespace Seaman\Plugin\Mongodb;

use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;
use Seaman\ValueObject\HealthCheck;

#[AsSeamanPlugin(
    name: 'seaman/mongodb',
    version: '1.0.0',
    description: 'MongoDB NoSQL database service for Seaman',
)]
final class MongodbPlugin implements PluginInterface
{
    private ConfigSchema $schema;

    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct()
    {
        $this->schema = ConfigSchema::create()
            ->string('version', default: '7')
            ->integer('port', default: 27017, min: 1, max: 65535)
            ->string('database', default: 'seaman')
            ->string('user', default: 'seaman')
            ->string('password', default: 'seaman');

        $this->config = $this->schema->validate([]);
    }

    public function getName(): string
    {
        return 'seaman/mongodb';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'MongoDB NoSQL database service for Seaman';
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

    #[ProvidesService(name: 'mongodb', category: ServiceCategory::Database)]
    public function mongodbService(): ServiceDefinition
    {
        return new ServiceDefinition(
            name: 'mongodb',
            template: __DIR__ . '/../templates/mongodb.yaml.twig',
            displayName: 'MongoDB',
            description: 'Document-oriented NoSQL database',
            icon: '🍃',
            category: ServiceCategory::Database,
            ports: [/* @phpstan-ignore cast.int */ (int) ($this->config['port'] ?? 0)],
            internalPorts: [27017],
            defaultConfig: [
                'version' => $this->config['version'],
                'port' => $this->config['port'],
                'database' => $this->config['database'],
                'user' => $this->config['user'],
                'password' => $this->config['password'],
            ],
            healthCheck: new HealthCheck(
                test: ['CMD', 'mongosh', '--eval', 'db.adminCommand("ping")'],
                interval: '10s',
                timeout: '5s',
                retries: 5,
            ),
        );
    }
}
