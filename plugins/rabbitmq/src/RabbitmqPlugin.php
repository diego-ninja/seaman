<?php

declare(strict_types=1);

// ABOUTME: RabbitMQ bundled plugin for Seaman.
// ABOUTME: Provides RabbitMQ message broker with management UI.

namespace Seaman\Plugin\Rabbitmq;

use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;
use Seaman\ValueObject\HealthCheck;

#[AsSeamanPlugin(
    name: 'seaman/rabbitmq',
    version: '1.0.0',
    description: 'RabbitMQ message broker for Seaman',
)]
final class RabbitmqPlugin implements PluginInterface
{
    private ConfigSchema $schema;

    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct()
    {
        $this->schema = ConfigSchema::create()
            ->string('version', default: '3-management')
            ->integer('port', default: 5672, min: 1, max: 65535)
            ->integer('management_port', default: 15672, min: 1, max: 65535)
            ->string('user', default: 'seaman')
            ->string('password', default: 'seaman');

        $this->config = $this->schema->validate([]);
    }

    public function getName(): string
    {
        return 'seaman/rabbitmq';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'RabbitMQ message broker for Seaman';
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

    #[ProvidesService(name: 'rabbitmq', category: ServiceCategory::Queue)]
    public function rabbitmqService(): ServiceDefinition
    {
        return new ServiceDefinition(
            name: 'rabbitmq',
            template: __DIR__ . '/../templates/rabbitmq.yaml.twig',
            displayName: 'RabbitMQ',
            description: 'Open-source message broker with management UI',
            icon: '🐰',
            category: ServiceCategory::Queue,
            ports: [/* @phpstan-ignore cast.int */ (int) ($this->config['port'] ?? 0), /* @phpstan-ignore cast.int */ (int) ($this->config['management_port'] ?? 0)],
            internalPorts: [5672, 15672],
            defaultConfig: [
                'version' => $this->config['version'],
                'port' => $this->config['port'],
                'management_port' => $this->config['management_port'],
                'user' => $this->config['user'],
                'password' => $this->config['password'],
            ],
            healthCheck: new HealthCheck(
                test: ['CMD-SHELL', 'rabbitmq-diagnostics -q ping'],
                interval: '10s',
                timeout: '5s',
                retries: 5,
            ),
        );
    }
}
