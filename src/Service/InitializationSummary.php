<?php

declare(strict_types=1);

// ABOUTME: Displays initialization configuration summary.
// ABOUTME: Formats and presents selected options before final confirmation.

namespace Seaman\Service;

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
    ): void {
        $formattedServices = $this->formatServiceList($services);

        summary(
            title: 'Seaman Configuration',
            icon: '⚙',
            data: [
                'Project Type' => $projectType->getLabel(),
                'Docker image' => 'seaman/seaman-php' . $phpConfig->version->value . ':latest',
                'PHP Version' => $phpConfig->version->value,
                'Database' => $database->name,
                'Services' => $formattedServices,
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
}
