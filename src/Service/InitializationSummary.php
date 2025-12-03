<?php

declare(strict_types=1);

// ABOUTME: Displays initialization configuration summary.
// ABOUTME: Formats and presents selected options before final confirmation.

namespace Seaman\Service;

use Seaman\Enum\ProjectType;
use Seaman\Enum\Service;
use Seaman\UI\Terminal;
use Seaman\ValueObject\PhpConfig;

use function box;

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

        box(
            title: Terminal::render('<fg=cyan>⚙</> Seaman Configuration') ?? 'Seaman Configuration',
            message: Terminal::render("\n"
            . '🔹<fg=cyan>Project Type</>: ' . $projectType->getLabel() . "\n"
            . '🔹<fg=cyan>Docker image</>: seaman/seaman-php' . $phpConfig->version->value . ':latest' . "\n"
            . '🔹<fg=cyan>PHP Version</>: ' . $phpConfig->version->value . "\n"
            . '🔹<fg=cyan>Database</>: ' . $database->name . "\n"
            . '🔹<fg=cyan>Services</>: ' . $formattedServices . "\n"
            . '🔹<fg=cyan>Xdebug</>: ' . ($phpConfig->xdebug->enabled ? 'Enabled' : 'Disabled') . "\n"
            . '🔹<fg=cyan>DevContainer</>: ' . ($devContainer ? 'Enabled' : 'Disabled') . "\n") ?? 'Unable to render seaman configuration',
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
