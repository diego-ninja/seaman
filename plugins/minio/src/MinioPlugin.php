<?php

declare(strict_types=1);

// ABOUTME: MinIO bundled plugin for Seaman.
// ABOUTME: Provides MinIO S3-compatible object storage service.

namespace Seaman\Plugin\Minio;

use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;
use Seaman\ValueObject\HealthCheck;

#[AsSeamanPlugin(
    name: 'seaman/minio',
    version: '1.0.0',
    description: 'MinIO S3-compatible storage for Seaman',
)]
final class MinioPlugin implements PluginInterface
{
    private ConfigSchema $schema;

    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct()
    {
        $this->schema = ConfigSchema::create()
            ->string('version', default: 'RELEASE.2024-11-07T00-52-20Z')
                ->label('MinIO version')
                ->description('Docker image tag to use')
                ->enum(['RELEASE.2024-11-07T00-52-20Z', 'latest'])
            ->integer('port', default: 9000, min: 1, max: 65535)
                ->label('API port')
                ->description('Host port for S3 API')
            ->integer('console_port', default: 9001, min: 1, max: 65535)
                ->label('Console port')
                ->description('Host port for web console')
            ->string('root_user', default: 'minioadmin')
                ->label('Root user')
                ->description('Username for root access')
            ->string('root_password', default: 'minioadmin')
                ->label('Root password')
                ->description('Password for root user')
                ->secret();

        $this->config = $this->schema->validate([]);
    }

    public function getName(): string
    {
        return 'seaman/minio';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'MinIO S3-compatible storage for Seaman';
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

    #[ProvidesService(name: 'minio', category: ServiceCategory::Storage)]
    public function minioService(): ServiceDefinition
    {
        $port = $this->config['port'];
        $consolePort = $this->config['console_port'];
        assert(is_int($port));
        assert(is_int($consolePort));

        return new ServiceDefinition(
            name: 'minio',
            template: __DIR__ . '/../templates/minio.yaml.twig',
            displayName: 'MinIO',
            description: 'S3-compatible object storage',
            icon: '🗄️',
            category: ServiceCategory::Storage,
            ports: [$port, $consolePort],
            internalPorts: [9000, 9001],
            defaultConfig: [
                'version' => $this->config['version'],
                'port' => $this->config['port'],
                'console_port' => $this->config['console_port'],
                'root_user' => $this->config['root_user'],
                'root_password' => $this->config['root_password'],
            ],
            healthCheck: new HealthCheck(
                test: ['CMD', 'curl', '-f', 'http://localhost:9000/minio/health/live'],
                interval: '10s',
                timeout: '5s',
                retries: 5,
            ),
            configSchema: $this->schema,
        );
    }
}
