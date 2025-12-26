<?php

declare(strict_types=1);

// ABOUTME: Mailpit bundled plugin for Seaman.
// ABOUTME: Provides Mailpit email testing service with web UI.

namespace Seaman\Plugin\Mailpit;

use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\Config\ConfigSchema;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;
use Seaman\ValueObject\HealthCheck;

#[AsSeamanPlugin(
    name: 'seaman/mailpit',
    version: '1.0.0',
    description: 'Mailpit email testing service for Seaman',
)]
final class MailpitPlugin implements PluginInterface
{
    private ConfigSchema $schema;

    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct()
    {
        $this->schema = ConfigSchema::create()
            ->string('version', default: 'v1.21')
                ->label('Mailpit version')
                ->description('Docker image tag to use')
                ->enum(['v1.20', 'v1.21', 'v1.22', 'latest'])
            ->integer('port', default: 8025, min: 1, max: 65535)
                ->label('Web UI port')
                ->description('Host port for web interface')
            ->integer('smtp_port', default: 1025, min: 1, max: 65535)
                ->label('SMTP port')
                ->description('Host port for SMTP server');

        $this->config = $this->schema->validate([]);
    }

    public function getName(): string
    {
        return 'seaman/mailpit';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'Mailpit email testing service for Seaman';
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

    #[ProvidesService(name: 'mailpit', category: ServiceCategory::Utility)]
    public function mailpitService(): ServiceDefinition
    {
        $port = $this->config['port'];
        $smtpPort = $this->config['smtp_port'];
        assert(is_int($port));
        assert(is_int($smtpPort));

        return new ServiceDefinition(
            name: 'mailpit',
            template: __DIR__ . '/../templates/mailpit.yaml.twig',
            displayName: 'Mailpit',
            description: 'Email testing tool with web UI',
            icon: '📧',
            category: ServiceCategory::Utility,
            ports: [$port, $smtpPort],
            internalPorts: [8025, 1025],
            defaultConfig: [
                'version' => $this->config['version'],
                'port' => $this->config['port'],
                'smtp_port' => $this->config['smtp_port'],
            ],
            healthCheck: new HealthCheck(
                test: ['CMD', 'wget', '--spider', '-q', 'http://localhost:8025/livez'],
                interval: '10s',
                timeout: '5s',
                retries: 5,
            ),
            configSchema: $this->schema,
        );
    }
}
