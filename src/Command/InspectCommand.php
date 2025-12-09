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

        $table->setHeaders(['SERVICE', 'STATUS', 'URL/PORT', 'INFO']);

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
                $dbUrls = $this->buildDatabaseUrls($serviceConfig);
                $table->addRow($this->buildServiceRow(
                    $serviceConfig->type,
                    $name,
                    $dbStatus,
                    $dbUrls,
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
            $serviceUrls = $this->buildServiceUrls($serviceConfig, $proxy);
            $serviceInfo = $this->getServiceInfo($serviceConfig);
            $table->addRow($this->buildServiceRow(
                $serviceConfig->type,
                $name,
                $status,
                $serviceUrls,
                $serviceInfo,
                $dozzleAvailable,
            ));
        }

        // Traefik (if enabled)
        if ($proxy->enabled) {
            $table->addSeparator();
            $traefikStatus = $statuses['traefik'] ?? null;
            $traefikUrls = $this->buildTraefikUrls($proxy);
            $table->addRow($this->buildServiceRow(
                Service::Traefik,
                'traefik',
                $traefikStatus,
                $traefikUrls,
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

    /**
     * Build URL list for a service with InDocker ports.
     *
     * @return list<string>
     */
    private function buildServiceUrls(ServiceConfig $service, ProxyConfig $proxy): array
    {
        $name = $service->name;
        $type = $service->type;
        $port = $service->port;
        $internalPorts = $this->getInternalPorts($type, $port);

        if (!$proxy->enabled) {
            $urls = ["localhost:{$port}"];
            if (!empty($internalPorts)) {
                $urls[] = 'InDocker:';
                foreach ($internalPorts as $internalPort) {
                    $urls[] = "  - {$name}:{$internalPort}";
                }
            }
            return $urls;
        }

        $externalUrl = match ($type) {
            Service::Mailpit => "https://mailpit.{$proxy->domainPrefix}.local",
            Service::Dozzle => "https://dozzle.{$proxy->domainPrefix}.local",
            Service::MinIO => "https://minio.{$proxy->domainPrefix}.local",
            Service::RabbitMq => "https://rabbitmq.{$proxy->domainPrefix}.local",
            Service::OpenSearch => "https://opensearch.{$proxy->domainPrefix}.local",
            Service::Elasticsearch => "https://elasticsearch.{$proxy->domainPrefix}.local",
            default => "localhost:{$port}",
        };

        $urls = [$externalUrl];
        if (!empty($internalPorts)) {
            $urls[] = 'InDocker:';
            foreach ($internalPorts as $internalPort) {
                $urls[] = "  - {$name}:{$internalPort}";
            }
        }

        return $urls;
    }

    /**
     * Get internal Docker ports for a service type.
     *
     * @return list<int>
     */
    private function getInternalPorts(Service $type, int $defaultPort): array
    {
        return match ($type) {
            // Databases
            Service::MySQL, Service::MariaDB => [3306],
            Service::PostgreSQL => [5432],
            Service::MongoDB => [27017],
            Service::Redis, Service::Valkey => [6379],
            Service::Memcached => [11211],

            // Message queues
            Service::RabbitMq => [5672, 15672],
            Service::Kafka => [9092],

            // Storage
            Service::MinIO => [9000, 9001],

            // Search
            Service::Elasticsearch, Service::OpenSearch => [9200],

            // Real-time
            Service::Mercure => [3000],
            Service::Soketi => [6001],

            // Utility
            Service::Mailpit => [8025, 1025],
            Service::Dozzle => [8080],

            // Traefik
            Service::Traefik => [80, 443, 8080],

            default => [$defaultPort],
        };
    }

    /**
     * Build URL list for database services.
     *
     * @return list<string>
     */
    private function buildDatabaseUrls(ServiceConfig $service): array
    {
        $name = $service->name;
        $port = $service->port;
        $internalPorts = $this->getInternalPorts($service->type, $port);

        $urls = ["localhost:{$port}"];
        if (!empty($internalPorts)) {
            $urls[] = 'InDocker:';
            foreach ($internalPorts as $internalPort) {
                $urls[] = "  - {$name}:{$internalPort}";
            }
        }

        return $urls;
    }

    /**
     * Build URL list for Traefik.
     *
     * @return list<string>
     */
    private function buildTraefikUrls(ProxyConfig $proxy): array
    {
        return [
            "https://traefik.{$proxy->domainPrefix}.local",
            'InDocker:',
            '  - traefik:80',
            '  - traefik:443',
            '  - traefik:8080',
        ];
    }

    /**
     * Get relevant info (credentials, version, etc.) for a service.
     */
    private function getServiceInfo(ServiceConfig $service): string
    {
        $env = $service->environmentVariables;

        return match ($service->type) {
            // Databases with credentials
            Service::MySQL,
            Service::MariaDB => sprintf(
                'v%s | %s:%s',
                $service->version,
                $env['MYSQL_USER'] ?? 'seaman',
                $env['MYSQL_PASSWORD'] ?? 'seaman',
            ),
            Service::PostgreSQL => sprintf(
                'v%s | %s:%s',
                $service->version,
                $env['POSTGRES_USER'] ?? 'seaman',
                $env['POSTGRES_PASSWORD'] ?? 'seaman',
            ),
            Service::MongoDB => sprintf(
                'v%s | %s:%s',
                $service->version,
                $env['MONGO_INITDB_ROOT_USERNAME'] ?? 'seaman',
                $env['MONGO_INITDB_ROOT_PASSWORD'] ?? 'seaman',
            ),

            // Message queues
            Service::RabbitMq => sprintf(
                'v%s | %s:%s',
                $service->version,
                $env['RABBITMQ_DEFAULT_USER'] ?? 'seaman',
                $env['RABBITMQ_DEFAULT_PASS'] ?? 'seaman',
            ),
            Service::Kafka => "v{$service->version}",

            // Cache/storage
            Service::Redis,
            Service::Valkey,
            Service::Memcached => "v{$service->version}",
            Service::MinIO => sprintf(
                'v%s | %s:%s',
                $service->version,
                $env['MINIO_ROOT_USER'] ?? 'minioadmin',
                $env['MINIO_ROOT_PASSWORD'] ?? 'minioadmin',
            ),

            // Search
            Service::Elasticsearch,
            Service::OpenSearch => "v{$service->version}",

            // Real-time
            Service::Mercure => "v{$service->version}",
            Service::Soketi => sprintf(
                'v%s | %s:%s',
                $service->version,
                $env['SOKETI_DEFAULT_APP_KEY'] ?? 'app-key',
                $env['SOKETI_DEFAULT_APP_SECRET'] ?? 'app-secret',
            ),

            // Utility
            Service::Mailpit => 'SMTP: localhost:1025',
            Service::Dozzle => 'Log viewer',

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
