<?php

declare(strict_types=1);

// ABOUTME: Soketi bundled plugin for Seaman.
// ABOUTME: Provides Soketi WebSocket server (Pusher-compatible).

namespace Seaman\Plugin\Soketi;

use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;
use Seaman\ValueObject\HealthCheck;

#[AsSeamanPlugin(
    name: 'seaman/soketi',
    version: '1.0.0',
    description: 'Soketi WebSocket server for Seaman',
)]
final class SoketiPlugin implements PluginInterface
{
    private ConfigSchema $schema;

    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct()
    {
        $this->schema = ConfigSchema::create()
            ->string('version', default: 'latest-16-alpine')
            ->integer('port', default: 6001, min: 1, max: 65535)
            ->integer('metrics_port', default: 9601, min: 1, max: 65535)
            ->string('app_id', default: 'app-id')
            ->string('app_key', default: 'app-key')
            ->string('app_secret', default: 'app-secret');

        $this->config = $this->schema->validate([]);
    }

    public function getName(): string
    {
        return 'seaman/soketi';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'Soketi WebSocket server for Seaman';
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

    #[ProvidesService(name: 'soketi', category: ServiceCategory::Utility)]
    public function soketiService(): ServiceDefinition
    {
        return new ServiceDefinition(
            name: 'soketi',
            template: __DIR__ . '/../templates/soketi.yaml.twig',
            displayName: 'Soketi',
            description: 'Pusher-compatible WebSocket server',
            icon: '🔌',
            category: ServiceCategory::Utility,
            ports: [/* @phpstan-ignore cast.int */ (int) ($this->config['port'] ?? 0), /* @phpstan-ignore cast.int */ (int) ($this->config['metrics_port'] ?? 0)],
            internalPorts: [6001, 9601],
            defaultConfig: [
                'version' => $this->config['version'],
                'port' => $this->config['port'],
                'metrics_port' => $this->config['metrics_port'],
                'app_id' => $this->config['app_id'],
                'app_key' => $this->config['app_key'],
                'app_secret' => $this->config['app_secret'],
            ],
            healthCheck: new HealthCheck(
                test: ['CMD', 'wget', '--spider', '-q', 'http://localhost:6001'],
                interval: '10s',
                timeout: '5s',
                retries: 5,
            ),
        );
    }
}
