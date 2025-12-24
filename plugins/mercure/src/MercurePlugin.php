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
            ->string('version', default: 'v0.16')
                ->label('Mercure version')
                ->description('Docker image tag to use')
                ->enum(['v0.15', 'v0.16', 'latest'])
            ->integer('port', default: 3000, min: 1, max: 65535)
                ->label('Port')
                ->description('Host port for Mercure hub')
            ->string('jwt_secret', default: '!ChangeThisMercureHubJWTSecretKey!')
                ->label('JWT secret')
                ->description('Secret key for JWT token signing')
                ->secret();

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
        $port = $this->config['port'];
        assert(is_int($port));

        return new ServiceDefinition(
            name: 'mercure',
            template: __DIR__ . '/../templates/mercure.yaml.twig',
            displayName: 'Mercure',
            description: 'Real-time updates hub for Symfony UX Turbo',
            icon: '⚡',
            category: ServiceCategory::Utility,
            ports: [$port],
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
            configSchema: $this->schema,
        );
    }
}
