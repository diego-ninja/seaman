<?php

declare(strict_types=1);

// ABOUTME: Displays initialization configuration summary.
// ABOUTME: Formats and presents selected options before final confirmation.

namespace Seaman\Service;

use Seaman\Enum\ProjectType;
use Seaman\Enum\Service;
use Seaman\UI\Terminal;
use Seaman\ValueObject\PhpConfig;

use function Laravel\Prompts\box;

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
    ): void {
        $formattedServices = $this->formatServiceList($services);

        box(
            title: Terminal::render('<fg=cyan>⚙</> Seaman Configuration') ?? 'Seaman Configuration',
            message: "\n" . '🔹Project Type: ' . $projectType->getLabel() . "\n"
            . '🔹Database: ' . $database->name . "\n"
            . '🔹Services: ' . $formattedServices . "\n"
            . '🔹PHP Version: ' . $phpConfig->version->value . "\n"
            . '🔹Xdebug: ' . ($phpConfig->xdebug->enabled ? 'Enabled' : 'Disabled') . "\n\n"
            . 'This will create:' . "\n"
            . '🔹.seaman/ directory' . "\n"
            . '🔹docker-compose.yaml' . "\n"
            . '🔹Docker image: seaman/seaman-php' . $phpConfig->version->value . ':latest' . "\n\n",
            color: 'cyan',
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
