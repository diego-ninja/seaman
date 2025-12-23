<?php

declare(strict_types=1);

// ABOUTME: Elasticsearch bundled plugin for Seaman.
// ABOUTME: Provides Elasticsearch search engine service with health checks.

namespace Seaman\Plugin\Elasticsearch;

use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;
use Seaman\ValueObject\HealthCheck;

#[AsSeamanPlugin(
    name: 'seaman/elasticsearch',
    version: '1.0.0',
    description: 'Elasticsearch search engine for Seaman',
)]
final class ElasticsearchPlugin implements PluginInterface
{
    private ConfigSchema $schema;

    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct()
    {
        $this->schema = ConfigSchema::create()
            ->string('version', default: '8.12.0')
            ->integer('port', default: 9200, min: 1, max: 65535)
            ->boolean('security_enabled', default: false);

        $this->config = $this->schema->validate([]);
    }

    public function getName(): string
    {
        return 'seaman/elasticsearch';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'Elasticsearch search engine for Seaman';
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

    #[ProvidesService(name: 'elasticsearch', category: ServiceCategory::Search)]
    public function elasticsearchService(): ServiceDefinition
    {
        return new ServiceDefinition(
            name: 'elasticsearch',
            template: __DIR__ . '/../templates/elasticsearch.yaml.twig',
            displayName: 'Elasticsearch',
            description: 'Distributed search and analytics engine',
            icon: '🔍',
            category: ServiceCategory::Search,
            ports: [/* @phpstan-ignore cast.int */ (int) ($this->config['port'] ?? 0)],
            internalPorts: [9200, 9300],
            defaultConfig: [
                'version' => $this->config['version'],
                'port' => $this->config['port'],
                'security_enabled' => $this->config['security_enabled'],
            ],
            healthCheck: new HealthCheck(
                test: ['CMD-SHELL', 'curl -f http://localhost:9200/_cluster/health || exit 1'],
                interval: '10s',
                timeout: '5s',
                retries: 5,
            ),
        );
    }
}
