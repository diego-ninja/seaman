<?php

declare(strict_types=1);

// ABOUTME: OpenSearch bundled plugin for Seaman.
// ABOUTME: Provides OpenSearch search and analytics engine with health checks.

namespace Seaman\Plugin\Opensearch;

use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;
use Seaman\ValueObject\HealthCheck;

#[AsSeamanPlugin(
    name: 'seaman/opensearch',
    version: '1.0.0',
    description: 'OpenSearch search and analytics engine for Seaman',
)]
final class OpensearchPlugin implements PluginInterface
{
    private ConfigSchema $schema;

    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct()
    {
        $this->schema = ConfigSchema::create()
            ->string('version', default: '2')
            ->integer('port', default: 9200, min: 1, max: 65535)
            ->integer('performance_port', default: 9600, min: 1, max: 65535);

        $this->config = $this->schema->validate([]);
    }

    public function getName(): string
    {
        return 'seaman/opensearch';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'OpenSearch search and analytics engine for Seaman';
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

    #[ProvidesService(name: 'opensearch', category: ServiceCategory::Search)]
    public function opensearchService(): ServiceDefinition
    {
        $port = $this->config['port'];
        $performancePort = $this->config['performance_port'];
        assert(is_int($port));
        assert(is_int($performancePort));

        return new ServiceDefinition(
            name: 'opensearch',
            template: __DIR__ . '/../templates/opensearch.yaml.twig',
            displayName: 'OpenSearch',
            description: 'Open-source search and analytics suite',
            icon: '🔎',
            category: ServiceCategory::Search,
            ports: [$port, $performancePort],
            internalPorts: [9200, 9600],
            defaultConfig: [
                'version' => $this->config['version'],
                'port' => $this->config['port'],
                'performance_port' => $this->config['performance_port'],
            ],
            healthCheck: new HealthCheck(
                test: ['CMD-SHELL', 'curl -s http://localhost:9200/_cluster/health | grep -q -E "green|yellow"'],
                interval: '10s',
                timeout: '5s',
                retries: 5,
            ),
        );
    }
}
