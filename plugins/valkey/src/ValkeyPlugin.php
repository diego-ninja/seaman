<?php

declare(strict_types=1);

// ABOUTME: Valkey bundled plugin for Seaman.
// ABOUTME: Provides Valkey cache service (Redis fork) with health checks.

namespace Seaman\Plugin\Valkey;

use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;
use Seaman\ValueObject\HealthCheck;

#[AsSeamanPlugin(
    name: 'seaman/valkey',
    version: '1.0.0',
    description: 'Valkey cache service for Seaman',
)]
final class ValkeyPlugin implements PluginInterface
{
    private ConfigSchema $schema;

    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct()
    {
        $this->schema = ConfigSchema::create()
            ->string('version', default: '8-alpine')
            ->integer('port', default: 6379, min: 1, max: 65535);

        $this->config = $this->schema->validate([]);
    }

    public function getName(): string
    {
        return 'seaman/valkey';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'Valkey cache service for Seaman';
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

    #[ProvidesService(name: 'valkey', category: ServiceCategory::Cache)]
    public function valkeyService(): ServiceDefinition
    {
        $port = $this->config['port'];
        assert(is_int($port));

        return new ServiceDefinition(
            name: 'valkey',
            template: __DIR__ . '/../templates/valkey.yaml.twig',
            displayName: 'Valkey',
            description: 'Redis-compatible in-memory data store',
            icon: '🔑',
            category: ServiceCategory::Cache,
            ports: [$port],
            internalPorts: [6379],
            defaultConfig: [
                'version' => $this->config['version'],
                'port' => $this->config['port'],
            ],
            healthCheck: new HealthCheck(
                test: ['CMD', 'valkey-cli', 'ping'],
                interval: '10s',
                timeout: '5s',
                retries: 5,
            ),
        );
    }
}
