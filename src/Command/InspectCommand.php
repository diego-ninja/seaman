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
use Seaman\UI\Terminal;
use Seaman\ValueObject\Configuration;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\table;

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

        $this->displayHeader($config, $projectRoot);
        $this->displayServices($config, $statuses);

        return Command::SUCCESS;
    }

    private function displayHeader(Configuration $config, string $projectRoot): void
    {
        $proxy = $config->proxy();
        $mainUrl = $proxy->enabled
            ? "https://{$proxy->getDomain()}"
            : "http://localhost:8000";

        Terminal::output()->writeln('');
        Terminal::output()->writeln(sprintf(
            '  <fg=cyan>Project:</> %s <fg=gray>%s</> %s',
            $config->projectName,
            $projectRoot,
            $mainUrl,
        ));
        Terminal::output()->writeln(sprintf(
            '  <fg=cyan>PHP:</> %s <fg=gray>|</> <fg=cyan>Xdebug:</> %s <fg=gray>|</> <fg=cyan>Proxy:</> %s',
            $config->php->version->value,
            $config->php->xdebug->enabled ? '<fg=green>enabled</>' : '<fg=gray>disabled</>',
            $proxy->enabled ? '<fg=green>Traefik</>' : '<fg=gray>disabled</>',
        ));
        Terminal::output()->writeln('');
    }

    /**
     * @param array<string, array{state: string, health: string, ports: string}> $statuses
     */
    private function displayServices(Configuration $config, array $statuses): void
    {
        $proxy = $config->proxy();
        $rows = [];

        // App service
        $appStatus = $statuses['app'] ?? null;
        $appUrl = $proxy->enabled
            ? "https://{$proxy->getDomain()}"
            : 'http://localhost:8000';
        $rows[] = $this->buildServiceRow(
            Service::App,
            'app',
            $appStatus,
            $appUrl,
            "PHP {$config->php->version->value}",
        );

        // Database service
        foreach ($config->services->enabled() as $name => $serviceConfig) {
            if (in_array($serviceConfig->type->value, Service::databases(), true)) {
                $dbStatus = $statuses[$name] ?? null;
                $dbInfo = "{$serviceConfig->type->name} {$serviceConfig->version}";
                $dbUrl = $dbStatus !== null ? "localhost:{$serviceConfig->port}" : '';
                $rows[] = $this->buildServiceRow(
                    $serviceConfig->type,
                    $name,
                    $dbStatus,
                    $dbUrl,
                    $dbInfo,
                );
            }
        }

        // Other services
        foreach ($config->services->enabled() as $name => $serviceConfig) {
            if (in_array($serviceConfig->type->value, Service::databases(), true)) {
                continue; // Skip databases, already shown
            }

            $status = $statuses[$name] ?? null;
            $url = $this->getServiceUrl($serviceConfig->type, $proxy, $serviceConfig->port);
            $rows[] = $this->buildServiceRow(
                $serviceConfig->type,
                $name,
                $status,
                $url,
                '',
            );
        }

        // Traefik (if enabled)
        if ($proxy->enabled) {
            $traefikStatus = $statuses['traefik'] ?? null;
            $traefikUrl = "https://traefik.{$proxy->domainPrefix}.local";
            $rows[] = $this->buildServiceRow(
                Service::Traefik,
                'traefik',
                $traefikStatus,
                $traefikUrl,
                'Dashboard',
            );
        }

        // Custom services
        if ($config->hasCustomServices()) {
            foreach ($config->customServices->names() as $name) {
                $status = $statuses[$name] ?? null;
                $rows[] = [
                    "⚙️  {$name}",
                    $this->formatStatus($status),
                    '',
                    '<fg=gray>custom</>',
                ];
            }
        }

        table(['Service', 'Status', 'URL', 'Info'], $rows);
    }

    /**
     * @param array{state: string, health: string, ports: string}|null $status
     * @return list<string>
     */
    private function buildServiceRow(
        Service $type,
        string $name,
        ?array $status,
        string $url,
        string $info,
    ): array {
        return [
            sprintf('%s %s', $type->icon(), $name),
            $this->formatStatus($status),
            $url,
            $info,
        ];
    }

    /**
     * @param array{state: string, health: string, ports: string}|null $status
     */
    private function formatStatus(?array $status): string
    {
        if ($status === null) {
            return '<fg=gray>not running</>';
        }

        return match (strtolower($status['state'])) {
            'running' => $status['health'] !== ''
                ? sprintf('<fg=green>running</> (<fg=green>%s</>)', $status['health'])
                : '<fg=green>running</>',
            'exited' => '<fg=red>stopped</>',
            'restarting' => '<fg=yellow>restarting</>',
            default => "<fg=gray>{$status['state']}</>",
        };
    }

    private function getServiceUrl(Service $type, \Seaman\ValueObject\ProxyConfig $proxy, int $port): string
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
     * @return array<string, array{state: string, health: string, ports: string}>
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
            ];
        }

        return $statuses;
    }
}
