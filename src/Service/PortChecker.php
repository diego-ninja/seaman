<?php

// ABOUTME: Checks if TCP ports are available.
// ABOUTME: Detects port conflicts and identifies processes using ports.

declare(strict_types=1);

namespace Seaman\Service;

use Seaman\Exception\PortConflictException;
use Symfony\Component\Process\Process;

final class PortChecker
{
    /**
     * @param positive-int $port
     */
    public function isPortAvailable(int $port): bool
    {
        $process = new Process(['lsof', '-i', ":{$port}", '-sTCP:LISTEN']);
        $process->run();

        // Exit code 1 means port is free (no process found)
        return $process->getExitCode() === 1;
    }

    /**
     * @param positive-int $port
     */
    public function getProcessUsingPort(int $port): ?string
    {
        $process = new Process(['lsof', '-i', ":{$port}", '-sTCP:LISTEN']);
        $process->run();

        if ($process->getExitCode() === 0) {
            // Parse lsof output to get process name
            $output = trim($process->getOutput());
            $lines = explode("\n", $output);

            if (count($lines) > 1) {
                // Second line contains process info
                $parts = preg_split('/\s+/', $lines[1]);
                return $parts[0] ?? 'unknown';
            }
        }

        return null;
    }

    /**
     * @param array<positive-int> $ports
     * @return array<int, string> Map of port => process name for ports in use
     */
    public function checkPorts(array $ports): array
    {
        $conflicts = [];

        foreach ($ports as $port) {
            if (!$this->isPortAvailable($port)) {
                $process = $this->getProcessUsingPort($port) ?? 'unknown';
                $conflicts[$port] = $process;
            }
        }

        return $conflicts;
    }

    /**
     * @param positive-int $port
     * @throws PortConflictException
     */
    public function ensurePortAvailable(int $port, string $service): void
    {
        if (!$this->isPortAvailable($port)) {
            $process = $this->getProcessUsingPort($port) ?? 'unknown';
            throw PortConflictException::forPort($port, $service, $process);
        }
    }
}
