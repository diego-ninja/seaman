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
                ->label('RabbitMQ version')
                ->description('Docker image tag to use')
                ->enum(['3-management', '3-management-alpine', '4.0-management', 'latest'])
            ->integer('port', default: 5672, min: 1, max: 65535)
                ->label('AMQP port')
                ->description('Host port for AMQP protocol')
            ->integer('management_port', default: 15672, min: 1, max: 65535)
                ->label('Management UI port')
                ->description('Host port for web management interface')
            ->string('user', default: 'seaman')
                ->label('Username')
                ->description('Default user for authentication')
            ->string('password', default: 'seaman')
                ->label('Password')
                ->description('Password for default user')
                ->secret();

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
        $port = $this->config['port'];
        $managementPort = $this->config['management_port'];
        assert(is_int($port));
        assert(is_int($managementPort));

        return new ServiceDefinition(
            name: 'rabbitmq',
            template: __DIR__ . '/../templates/rabbitmq.yaml.twig',
            displayName: 'RabbitMQ',
            description: 'Open-source message broker with management UI',
            icon: '🐰',
            category: ServiceCategory::Queue,
            ports: [$port, $managementPort],
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
            configSchema: $this->schema,
        );
    }
}
