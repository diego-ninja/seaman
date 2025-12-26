<?php

declare(strict_types=1);

// ABOUTME: Displays detailed project configuration and status.
// ABOUTME: Similar to ddev describe - shows services, URLs, and runtime info.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Enum\OperatingMode;
use Seaman\Enum\Service;
use Seaman\Service\ConfigManager;
use Seaman\Service\Container\ServiceRegistry;
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
        private readonly ServiceRegistry $serviceRegistry,
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
                $service = $this->serviceRegistry->get($serviceConfig->type);
                $dbUrls = $this->buildServiceUrlsFromRegistry($serviceConfig, $proxy);
                $table->addRow($this->buildServiceRow(
                    $serviceConfig->type,
                    $name,
                    $dbStatus,
                    $dbUrls,
                    $service->getInspectInfo($serviceConfig),
                    $dozzleAvailable,
                    $service->getIcon(),
                ));
            }
        }

        // Other services (excluding Traefik, which is handled separately)
        foreach ($config->services->enabled() as $name => $serviceConfig) {
            if (in_array($serviceConfig->type->value, Service::databases(), true)) {
                continue;
            }
            if ($serviceConfig->type === Service::Traefik) {
                continue;
            }

            $table->addSeparator();
            $status = $statuses[$name] ?? null;
            $service = $this->serviceRegistry->get($serviceConfig->type);
            $serviceUrls = $this->buildServiceUrlsFromRegistry($serviceConfig, $proxy);
            $table->addRow($this->buildServiceRow(
                $serviceConfig->type,
                $name,
                $status,
                $serviceUrls,
                $service->getInspectInfo($serviceConfig),
                $dozzleAvailable,
                $service->getIcon(),
            ));
        }

        // Traefik (if enabled)
        if ($proxy->enabled) {
            $table->addSeparator();
            $traefikStatus = $statuses['traefik'] ?? null;
            $traefikService = $this->serviceRegistry->get(Service::Traefik);
            $traefikConfig = $traefikService->getDefaultConfig();
            $traefikUrls = $this->buildTraefikUrls($proxy, $traefikService->getInternalPorts());
            $table->addRow($this->buildServiceRow(
                Service::Traefik,
                'traefik',
                $traefikStatus,
                $traefikUrls,
                $traefikService->getInspectInfo($traefikConfig),
                $dozzleAvailable,
                $traefikService->getIcon(),
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
        ?string $serviceIcon = null,
    ): array {
        $icon = $serviceIcon ?? $type->icon();
        $serviceName = sprintf('%s %s', $icon, $name);

        // Add dozzle link if available and service is running
        if ($dozzleAvailable && $status !== null && $status['containerId'] !== '') {
            $serviceName = sprintf(
                '%s <href=http://localhost:9080/container/%s>%s</>',
                $icon,
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
     * Build URL list for a service using registry for internal ports.
     *
     * @return list<string>
     */
    private function buildServiceUrlsFromRegistry(ServiceConfig $serviceConfig, ProxyConfig $proxy): array
    {
        $name = $serviceConfig->name;
        $type = $serviceConfig->type;
        $port = $serviceConfig->port;
        $service = $this->serviceRegistry->get($type);
        $internalPorts = $service->getInternalPorts();

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
     * Build URL list for Traefik.
     *
     * @param list<int> $internalPorts
     * @return list<string>
     */
    private function buildTraefikUrls(ProxyConfig $proxy, array $internalPorts): array
    {
        $urls = ["https://traefik.{$proxy->domainPrefix}.local"];

        if (!empty($internalPorts)) {
            $urls[] = 'InDocker:';
            foreach ($internalPorts as $port) {
                $urls[] = "  - traefik:{$port}";
            }
        }

        return $urls;
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
