<?php

declare(strict_types=1);

// ABOUTME: Displays detailed project configuration and status.
// ABOUTME: Similar to ddev describe - shows services, URLs, and runtime info.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Enum\OperatingMode;
use Seaman\Enum\Service;
use Seaman\Service\ConfigManager;
use Seaman\Service\DockerManager;
use Seaman\UI\Widget\Table\Table;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\ProxyConfig;
use Seaman\ValueObject\ServiceConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'seaman:inspect',
    description: 'Display project configuration and status',
    aliases: ['inspect', 'describe'],
)]
class InspectCommand extends ModeAwareCommand implements Decorable
{
    public function __construct(
        private readonly ConfigManager $configManager,
        private readonly DockerManager $dockerManager,
    ) {
        parent::__construct();
    }

    public function supportsMode(OperatingMode $mode): bool
    {
        return $mode !== OperatingMode::Uninitialized;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->configManager->load();
        $statuses = $this->getServiceStatuses();
        $projectRoot = (string) getcwd();

        $table = $this->buildTable($config, $statuses, $projectRoot);
        $table->display();

        return Command::SUCCESS;
    }

    /**
     * Build the inspect table with fluent API.
     *
     * @param array<string, array{state: string, health: string, ports: string, containerId: string}> $statuses
     */
    private function buildTable(Configuration $config, array $statuses, string $projectRoot): Table
    {
        $table = new Table();

        // Build header lines
        foreach ($this->buildHeaderLines($config, $projectRoot) as $line) {
            $table->addHeaderLine($line);
        }

        $table->setHeaders(['SERVICE', 'STATUS', 'URL', 'INFO']);

        $proxy = $config->proxy();
        $dozzleAvailable = $this->isDozzleAvailable($statuses);

        // App service
        $appStatus = $statuses['app'] ?? null;
        $table->addRow($this->buildServiceRow(
            Service::App,
            'app',
            $appStatus,
            $this->buildAppUrls($proxy),
            "PHP {$config->php->version->value}",
            $dozzleAvailable,
        ));

        // Database service
        foreach ($config->services->enabled() as $name => $serviceConfig) {
            if (in_array($serviceConfig->type->value, Service::databases(), true)) {
                $table->addSeparator();
                $dbStatus = $statuses[$name] ?? null;
                $dbInfo = $this->getServiceInfo($serviceConfig);
                $table->addRow($this->buildServiceRow(
                    $serviceConfig->type,
                    $name,
                    $dbStatus,
                    "localhost:{$serviceConfig->port}",
                    $dbInfo,
                    $dozzleAvailable,
                ));
            }
        }

        // Other services
        foreach ($config->services->enabled() as $name => $serviceConfig) {
            if (in_array($serviceConfig->type->value, Service::databases(), true)) {
                continue;
            }

            $table->addSeparator();
            $status = $statuses[$name] ?? null;
            $url = $this->getServiceUrl($serviceConfig->type, $proxy, $serviceConfig->port);
            $serviceInfo = $this->getServiceInfo($serviceConfig);
            $table->addRow($this->buildServiceRow(
                $serviceConfig->type,
                $name,
                $status,
                $url,
                $serviceInfo,
                $dozzleAvailable,
            ));
        }

        // Traefik (if enabled)
        if ($proxy->enabled) {
            $table->addSeparator();
            $traefikStatus = $statuses['traefik'] ?? null;
            $table->addRow($this->buildServiceRow(
                Service::Traefik,
                'traefik',
                $traefikStatus,
                "https://traefik.{$proxy->domainPrefix}.local",
                'Dashboard',
                $dozzleAvailable,
            ));
        }

        // Custom services
        if ($config->hasCustomServices()) {
            foreach ($config->customServices->names() as $name) {
                $table->addSeparator();
                $status = $statuses[$name] ?? null;
                $customServiceName = "⚙️  {$name}";

                // Add dozzle link for custom services too
                if ($dozzleAvailable && $status !== null && $status['containerId'] !== '') {
                    $customServiceName = sprintf(
                        '⚙️  <href=http://localhost:9080/container/%s>%s</>',
                        $status['containerId'],
                        $name,
                    );
                }

                $table->addRow([
                    $customServiceName,
                    $this->formatStatus($status),
                    '',
                    'custom',
                ]);
            }
        }

        return $table;
    }

    /**
     * Build header lines for the table.
     *
     * @return list<string>
     */
    private function buildHeaderLines(Configuration $config, string $projectRoot): array
    {
        $proxy = $config->proxy();
        $lines = [];

        // Project name and path
        $lines[] = "Project:  {$config->projectName}  {$projectRoot}";

        // URL
        $mainUrl = $proxy->enabled
            ? "https://{$proxy->getDomain()}"
            : 'http://localhost:8000';
        $lines[] = "URL:      {$mainUrl}";

        // PHP version
        $lines[] = "PHP:      {$config->php->version->value}";

        // Proxy (only if enabled)
        if ($proxy->enabled) {
            $lines[] = "Proxy:    Traefik";

            // DNS provider (only if configured)
            if ($proxy->dnsProvider !== null) {
                $lines[] = "DNS:      {$proxy->dnsProvider->getDisplayName()}";
            }
        }

        // Xdebug
        $xdebugStatus = $config->php->xdebug->enabled ? 'enabled' : 'disabled';
        $lines[] = "Xdebug:   {$xdebugStatus}";

        return $lines;
    }

    /**
     * Build URL list for app service.
     *
     * @return list<string>
     */
    private function buildAppUrls(ProxyConfig $proxy): array
    {
        if (!$proxy->enabled) {
            return ['http://localhost:8000'];
        }

        return [
            "https://{$proxy->getDomain()}",
            'InDocker:',
            '  - app:80',
        ];
    }

    /**
     * @param array{state: string, health: string, ports: string, containerId: string}|null $status
     * @param string|list<string> $url
     * @return array{string, string, string|list<string>, string}
     */
    private function buildServiceRow(
        Service $type,
        string $name,
        ?array $status,
        string|array $url,
        string $info,
        bool $dozzleAvailable = false,
    ): array {
        $serviceName = sprintf('%s %s', $type->icon(), $name);

        // Add dozzle link if available and service is running
        if ($dozzleAvailable && $status !== null && $status['containerId'] !== '') {
            $serviceName = sprintf(
                '%s <href=http://localhost:9080/container/%s>%s</>',
                $type->icon(),
                $status['containerId'],
                $name,
            );
        }

        return [
            $serviceName,
            $this->formatStatus($status),
            $url,
            $info,
        ];
    }

    /**
     * @param array{state: string, health: string, ports: string, containerId: string}|null $status
     */
    private function formatStatus(?array $status): string
    {
        if ($status === null) {
            return '<fg=gray>⚙</> not running';
        }

        return match (strtolower($status['state'])) {
            'running' => $status['health'] !== ''
                ? sprintf('<fg=green>⚙</> running (<fg=green>%s</>)', $status['health'])
                : '<fg=yellow>⚙</> running (<fg=yellow>unknown</>)',
            'exited' => '<fg=red>⚙</> stopped',
            'restarting' => '<fg=yellow>⚙</> restarting',
            default => "<fg=gray>⚙</> {$status['state']}",
        };
    }

    private function getServiceUrl(Service $type, ProxyConfig $proxy, int $port): string
    {
        if (!$proxy->enabled) {
            return "localhost:{$port}";
        }

        return match ($type) {
            Service::Mailpit => "https://mailpit.{$proxy->domainPrefix}.local",
            Service::Dozzle => "https://dozzle.{$proxy->domainPrefix}.local",
            Service::MinIO => "https://minio.{$proxy->domainPrefix}.local",
            Service::RabbitMq => "https://rabbitmq.{$proxy->domainPrefix}.local",
            Service::Mercure => "https://mercure.{$proxy->domainPrefix}.local",
            default => "localhost:{$port}",
        };
    }

    /**
     * Get relevant info (credentials, version, etc.) for a service.
     */
    private function getServiceInfo(ServiceConfig $service): string
    {
        return match ($service->type) {
            // Databases with credentials
            Service::MySQL,
            Service::MariaDB => "v{$service->version} | seaman:secret",
            Service::PostgreSQL => "v{$service->version} | seaman:secret",
            Service::MongoDB => "v{$service->version}",

            // Message queues
            Service::RabbitMq => "v{$service->version} | seaman:secret",
            Service::Kafka => "v{$service->version}",

            // Cache/storage
            Service::Redis,
            Service::Valkey,
            Service::Memcached => "v{$service->version}",
            Service::MinIO => "v{$service->version} | seaman:seaman123",

            // Search
            Service::Elasticsearch,
            Service::OpenSearch => "v{$service->version}",

            // Real-time
            Service::Mercure => "v{$service->version}",
            Service::Soketi => "v{$service->version} | app-key:app-secret",

            // Utility
            Service::Mailpit => "SMTP: localhost:1025",
            Service::Dozzle => "Log viewer",

            default => "v{$service->version}",
        };
    }

    /**
     * Check if dozzle is running and available.
     *
     * @param array<string, array{state: string, health: string, ports: string, containerId: string}> $statuses
     */
    private function isDozzleAvailable(array $statuses): bool
    {
        $dozzleStatus = $statuses['dozzle'] ?? null;
        return $dozzleStatus !== null && strtolower($dozzleStatus['state']) === 'running';
    }

    /**
     * @return array<string, array{state: string, health: string, ports: string, containerId: string}>
     */
    private function getServiceStatuses(): array
    {
        try {
            $services = $this->dockerManager->status();
        } catch (\RuntimeException) {
            return [];
        }

        $statuses = [];
        foreach ($services as $service) {
            $serviceName = $service['Service'] ?? '';
            if ($serviceName === '') {
                continue;
            }

            /** @var list<array{PublishedPort?: int, Protocol?: string}> $publishers */
            $publishers = $service['Publishers'] ?? [];
            $ports = array_unique(array_filter(array_map(function (array $port): string {
                $publishedPort = $port['PublishedPort'] ?? 0;
                if ($publishedPort === 0) {
                    return '';
                }
                return (string) $publishedPort;
            }, $publishers)));

            $statuses[$serviceName] = [
                'state' => $service['State'] ?? 'unknown',
                'health' => $service['Health'] ?? '',
                'ports' => implode(', ', $ports),
                'containerId' => $service['ID'] ?? '',
            ];
        }

        return $statuses;
    }
}
