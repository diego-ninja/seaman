<?php

declare(strict_types=1);

// ABOUTME: Redis bundled plugin for Seaman.
// ABOUTME: Provides Redis cache service with health checks.

namespace Seaman\Plugin\Redis;

use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;
use Seaman\ValueObject\HealthCheck;

#[AsSeamanPlugin(
    name: 'seaman/redis',
    version: '1.0.0',
    description: 'Redis cache service for Seaman',
)]
final class RedisPlugin implements PluginInterface
{
    private ConfigSchema $schema;

    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct()
    {
        $this->schema = ConfigSchema::create()
            ->string('version', default: '7-alpine')
            ->integer('port', default: 6379, min: 1, max: 65535);

        $this->config = $this->schema->validate([]);
    }

    public function getName(): string
    {
        return 'seaman/redis';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'Redis cache service for Seaman';
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

    #[ProvidesService(name: 'redis', category: ServiceCategory::Cache)]
    public function redisService(): ServiceDefinition
    {
        return new ServiceDefinition(
            name: 'redis',
            template: __DIR__ . '/../templates/redis.yaml.twig',
            displayName: 'Redis',
            description: 'In-memory data store for caching and sessions',
            icon: '🧵',
            category: ServiceCategory::Cache,
            ports: [/* @phpstan-ignore cast.int */ (int) ($this->config['port'] ?? 0)],
            internalPorts: [6379],
            defaultConfig: [
                'version' => $this->config['version'],
                'port' => $this->config['port'],
            ],
            healthCheck: new HealthCheck(
                test: ['CMD', 'redis-cli', 'ping'],
                interval: '10s',
                timeout: '5s',
                retries: 5,
            ),
        );
    }
}
