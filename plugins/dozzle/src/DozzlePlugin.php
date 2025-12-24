<?php

declare(strict_types=1);

// ABOUTME: Dozzle bundled plugin for Seaman.
// ABOUTME: Provides Dozzle real-time Docker log viewer.

namespace Seaman\Plugin\Dozzle;

use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;
use Seaman\ValueObject\HealthCheck;

#[AsSeamanPlugin(
    name: 'seaman/dozzle',
    version: '1.0.0',
    description: 'Dozzle Docker log viewer for Seaman',
)]
final class DozzlePlugin implements PluginInterface
{
    private ConfigSchema $schema;

    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct()
    {
        $this->schema = ConfigSchema::create()
            ->string('version', default: 'v8.8')
                ->label('Dozzle version')
                ->description('Docker image tag to use')
                ->enum(['v8.7', 'v8.8', 'v8.9', 'latest'])
            ->integer('port', default: 8080, min: 1, max: 65535)
                ->label('Port')
                ->description('Host port for web interface');

        $this->config = $this->schema->validate([]);
    }

    public function getName(): string
    {
        return 'seaman/dozzle';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'Dozzle Docker log viewer for Seaman';
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

    #[ProvidesService(name: 'dozzle', category: ServiceCategory::Utility)]
    public function dozzleService(): ServiceDefinition
    {
        $port = $this->config['port'];
        assert(is_int($port));

        return new ServiceDefinition(
            name: 'dozzle',
            template: __DIR__ . '/../templates/dozzle.yaml.twig',
            displayName: 'Dozzle',
            description: 'Real-time Docker container log viewer',
            icon: '📋',
            category: ServiceCategory::Utility,
            ports: [$port],
            internalPorts: [8080],
            defaultConfig: [
                'version' => $this->config['version'],
                'port' => $this->config['port'],
            ],
            healthCheck: new HealthCheck(
                test: ['CMD', '/dozzle', 'healthcheck'],
                interval: '10s',
                timeout: '5s',
                retries: 5,
            ),
            configSchema: $this->schema,
        );
    }
}
