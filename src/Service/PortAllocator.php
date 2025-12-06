<?php

declare(strict_types=1);

// ABOUTME: Orchestrates interactive port allocation for services.
// ABOUTME: Detects conflicts and prompts user for confirmation on alternatives.

namespace Seaman\Service;

use Seaman\Exception\PortAllocationException;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\PortAllocation;

use function Laravel\Prompts\confirm;

final readonly class PortAllocator
{
    public function __construct(
        private PortChecker $portChecker,
    ) {}

    /**
     * Allocate ports for all enabled services.
     *
     * @throws PortAllocationException if user rejects or no ports available
     */
    public function allocate(Configuration $config): PortAllocation
    {
        $allocation = new PortAllocation();

        foreach ($config->services->enabled() as $name => $serviceConfig) {
            foreach ($serviceConfig->getAllPorts() as $desiredPort) {
                if ($desiredPort <= 0) {
                    continue; // SQLite doesn't use ports
                }

                /** @var positive-int $desiredPort */
                $allocation = $this->allocatePort($allocation, $name, $desiredPort);
            }
        }

        return $allocation;
    }

    /**
     * Allocate a single port, prompting user if occupied.
     *
     * @param positive-int $desiredPort
     * @throws PortAllocationException
     */
    private function allocatePort(PortAllocation $allocation, string $serviceName, int $desiredPort): PortAllocation
    {
        if ($this->portChecker->isPortAvailable($desiredPort)) {
            return $allocation->withPort($serviceName, $desiredPort, $desiredPort);
        }

        // Port is occupied - find alternative
        /** @var positive-int $nextPort */
        $nextPort = $desiredPort + 1;
        $alternativePort = $this->portChecker->findAvailablePort($nextPort);

        if ($alternativePort === null) {
            throw PortAllocationException::noPortsAvailable($serviceName, $desiredPort);
        }

        // Get process using the port for better UX
        $process = $this->portChecker->getProcessUsingPort($desiredPort) ?? 'another process';

        $confirmed = confirm(
            label: sprintf(
                'Port %d is in use by %s. Use %d for %s?',
                $desiredPort,
                $process,
                $alternativePort,
                $serviceName,
            ),
            default: true,
        );

        if (!$confirmed) {
            throw PortAllocationException::userRejected($serviceName, $desiredPort);
        }

        return $allocation->withPort($serviceName, $desiredPort, $alternativePort);
    }
}
