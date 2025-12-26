<?php

declare(strict_types=1);

// ABOUTME: Apache Kafka bundled plugin for Seaman.
// ABOUTME: Provides Kafka distributed event streaming platform.

namespace Seaman\Plugin\Kafka;

use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;
use Seaman\ValueObject\HealthCheck;

#[AsSeamanPlugin(
    name: 'seaman/kafka',
    version: '1.0.0',
    description: 'Apache Kafka event streaming for Seaman',
)]
final class KafkaPlugin implements PluginInterface
{
    private ConfigSchema $schema;

    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct()
    {
        $this->schema = ConfigSchema::create()
            ->string('version', default: '3.7')
                ->label('Kafka version')
                ->description('Docker image tag to use')
                ->enum(['3.6', '3.7', '3.8', '3.9', 'latest'])
            ->integer('port', default: 9092, min: 1, max: 65535)
                ->label('Port')
                ->description('Host port for Kafka broker');

        $this->config = $this->schema->validate([]);
    }

    public function getName(): string
    {
        return 'seaman/kafka';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'Apache Kafka event streaming for Seaman';
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

    #[ProvidesService(name: 'kafka', category: ServiceCategory::Queue)]
    public function kafkaService(): ServiceDefinition
    {
        $port = $this->config['port'];
        assert(is_int($port));

        return new ServiceDefinition(
            name: 'kafka',
            template: __DIR__ . '/../templates/kafka.yaml.twig',
            displayName: 'Apache Kafka',
            description: 'Distributed event streaming platform',
            icon: '📨',
            category: ServiceCategory::Queue,
            ports: [$port],
            internalPorts: [9092, 9093],
            defaultConfig: [
                'version' => $this->config['version'],
                'port' => $this->config['port'],
            ],
            healthCheck: new HealthCheck(
                test: ['CMD-SHELL', '/opt/kafka/bin/kafka-cluster.sh cluster-id --bootstrap-server localhost:9092 || exit 1'],
                interval: '10s',
                timeout: '10s',
                retries: 5,
            ),
            configSchema: $this->schema,
        );
    }
}
