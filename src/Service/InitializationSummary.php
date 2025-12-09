<?php

declare(strict_types=1);

// ABOUTME: Displays initialization configuration summary.
// ABOUTME: Formats and presents selected options before final confirmation.

namespace Seaman\Service;

use Seaman\Enum\DnsProvider;
use Seaman\Enum\ProjectType;
use Seaman\Enum\Service;
use Seaman\ValueObject\PhpConfig;

use function summary;

class InitializationSummary
{
    /**
     * Display configuration summary to the user.
     *
     * @param list<Service> $services
     */
    public function display(
        Service $database,
        array $services,
        PhpConfig $phpConfig,
        ProjectType $projectType,
        bool $devContainer,
        bool $proxyEnabled = true,
        bool $configureDns = false,
        ?DnsProvider $dnsProvider = null,
    ): void {
        $formattedServices = $this->formatServiceList($services);

        $dnsDisplay = $this->formatDnsDisplay($configureDns, $dnsProvider);

        summary(
            title: 'Seaman Configuration',
            icon: '⚙',
            data: [
                'Project Type' => $projectType->getLabel(),
                'Docker image' => 'seaman/seaman-php' . $phpConfig->version->value . ':latest',
                'PHP Version' => $phpConfig->version->value,
                'Database' => $database->name,
                'Services' => $formattedServices,
                'Reverse Proxy' => $proxyEnabled ? 'Traefik (HTTPS)' : 'Disabled (direct ports)',
                'DNS' => $dnsDisplay,
                'Xdebug' => $phpConfig->xdebug->enabled ? 'Enabled' : 'Disabled',
                'DevContainer' => $devContainer ? 'Enabled' : 'Disabled',
            ],
        );
    }

    /**
     * Format service list for display.
     *
     * @param list<Service> $services
     */
    public function formatServiceList(array $services): string
    {
        if (empty($services)) {
            return 'None';
        }

        return implode(', ', array_map(
            fn(Service $service): string => ucfirst($service->value),
            $services,
        ));
    }

    private function formatDnsDisplay(bool $configureDns, ?DnsProvider $dnsProvider): string
    {
        if (!$configureDns) {
            return 'Skip (manual /etc/hosts)';
        }

        if ($dnsProvider === null) {
            return 'Auto-detect';
        }

        return $dnsProvider->getDisplayName();
    }
}
