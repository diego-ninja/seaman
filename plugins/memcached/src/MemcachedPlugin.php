<?php

declare(strict_types=1);

// ABOUTME: Memcached bundled plugin for Seaman.
// ABOUTME: Provides Memcached cache service with health checks.

namespace Seaman\Plugin\Memcached;

use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;
use Seaman\ValueObject\HealthCheck;

#[AsSeamanPlugin(
    name: 'seaman/memcached',
    version: '1.0.0',
    description: 'Memcached cache service for Seaman',
)]
final class MemcachedPlugin implements PluginInterface
{
    private ConfigSchema $schema;

    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct()
    {
        $this->schema = ConfigSchema::create()
            ->string('version', default: '1.6-alpine')
                ->label('Memcached version')
                ->description('Docker image tag to use')
                ->enum(['1.6-alpine', 'alpine', 'latest'])
            ->integer('port', default: 11211, min: 1, max: 65535)
                ->label('Port')
                ->description('Host port to expose Memcached on');

        $this->config = $this->schema->validate([]);
    }

    public function getName(): string
    {
        return 'seaman/memcached';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'Memcached cache service for Seaman';
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

    #[ProvidesService(name: 'memcached', category: ServiceCategory::Cache)]
    public function memcachedService(): ServiceDefinition
    {
        $port = $this->config['port'];
        assert(is_int($port));

        return new ServiceDefinition(
            name: 'memcached',
            template: __DIR__ . '/../templates/memcached.yaml.twig',
            displayName: 'Memcached',
            description: 'High-performance distributed memory caching system',
            icon: '🗃️',
            category: ServiceCategory::Cache,
            ports: [$port],
            internalPorts: [11211],
            defaultConfig: [
                'version' => $this->config['version'],
                'port' => $this->config['port'],
            ],
            healthCheck: new HealthCheck(
                test: ['CMD-SHELL', 'echo version | nc -w 1 localhost 11211 | grep -q VERSION'],
                interval: '10s',
                timeout: '5s',
                retries: 5,
            ),
            configSchema: $this->schema,
        );
    }
}
