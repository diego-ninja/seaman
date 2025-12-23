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
            ->string('version', default: 'latest')
            ->integer('port', default: 8025, min: 1, max: 65535)
            ->integer('smtp_port', default: 1025, min: 1, max: 65535);

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
        return new ServiceDefinition(
            name: 'mailpit',
            template: __DIR__ . '/../templates/mailpit.yaml.twig',
            displayName: 'Mailpit',
            description: 'Email testing tool with web UI',
            icon: '📧',
            category: ServiceCategory::Utility,
            ports: [/* @phpstan-ignore cast.int */ (int) ($this->config['port'] ?? 0), /* @phpstan-ignore cast.int */ (int) ($this->config['smtp_port'] ?? 0)],
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
        );
    }
}
