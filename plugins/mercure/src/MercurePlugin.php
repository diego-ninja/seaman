<?php

declare(strict_types=1);

// ABOUTME: Mercure bundled plugin for Seaman.
// ABOUTME: Provides Mercure real-time updates hub for Symfony applications.

namespace Seaman\Plugin\Mercure;

use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;
use Seaman\ValueObject\HealthCheck;

#[AsSeamanPlugin(
    name: 'seaman/mercure',
    version: '1.0.0',
    description: 'Mercure real-time updates hub for Seaman',
)]
final class MercurePlugin implements PluginInterface
{
    private ConfigSchema $schema;

    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct()
    {
        $this->schema = ConfigSchema::create()
            ->string('version', default: 'latest')
            ->integer('port', default: 3000, min: 1, max: 65535)
            ->string('jwt_secret', default: '!ChangeThisMercureHubJWTSecretKey!');

        $this->config = $this->schema->validate([]);
    }

    public function getName(): string
    {
        return 'seaman/mercure';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'Mercure real-time updates hub for Seaman';
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

    #[ProvidesService(name: 'mercure', category: ServiceCategory::Utility)]
    public function mercureService(): ServiceDefinition
    {
        return new ServiceDefinition(
            name: 'mercure',
            template: __DIR__ . '/../templates/mercure.yaml.twig',
            displayName: 'Mercure',
            description: 'Real-time updates hub for Symfony UX Turbo',
            icon: '⚡',
            category: ServiceCategory::Utility,
            ports: [/* @phpstan-ignore cast.int */ (int) ($this->config['port'] ?? 0)],
            internalPorts: [3000],
            defaultConfig: [
                'version' => $this->config['version'],
                'port' => $this->config['port'],
                'jwt_secret' => $this->config['jwt_secret'],
            ],
            healthCheck: new HealthCheck(
                test: ['CMD', 'wget', '--spider', '-q', 'http://localhost:3000/.well-known/mercure'],
                interval: '10s',
                timeout: '5s',
                retries: 5,
            ),
        );
    }
}
