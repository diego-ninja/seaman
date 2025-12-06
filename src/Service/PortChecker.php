<?php

declare(strict_types=1);

// ABOUTME: Checks if TCP ports are available.
// ABOUTME: Detects port conflicts and identifies processes using ports.

namespace Seaman\Service;

use Seaman\Exception\PortConflictException;
use Symfony\Component\Process\Process;

final class PortChecker
{
    private const int MAX_ATTEMPTS = 10;

    /**
     * @param positive-int $port
     */
    public function isPortAvailable(int $port): bool
    {
        // Try connecting to the port - if connection succeeds, something is listening
        $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
        if ($connection !== false) {
            fclose($connection);
            return false; // Something is listening - port not available
        }

        return true; // Connection refused - port is available
    }

    /**
     * @param positive-int $port
     */
    public function getProcessUsingPort(int $port): ?string
    {
        // Try lsof first (macOS, some Linux)
        $process = new Process(['lsof', '-i', ":{$port}", '-sTCP:LISTEN']);
        $process->run();

        if ($process->getExitCode() === 0) {
            $output = trim($process->getOutput());
            $lines = explode("\n", $output);

            if (count($lines) > 1) {
                $parts = preg_split('/\s+/', $lines[1]);
                return $parts[0] ?? null;
            }
        }

        // Try ss (Linux)
        $process = new Process(['ss', '-tlnp', 'sport', '=', ":{$port}"]);
        $process->run();

        if ($process->getExitCode() === 0) {
            $output = trim($process->getOutput());
            // Parse ss output to find process name in users:(("name",pid=...))
            if (preg_match('/users:\(\("([^"]+)"/', $output, $matches)) {
                return $matches[1];
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

    /**
     * Find an available port starting from the desired port.
     *
     * @param positive-int $desiredPort
     * @param positive-int $maxAttempts
     * @return positive-int|null Available port, or null if none found within max attempts
     */
    public function findAvailablePort(int $desiredPort, int $maxAttempts = self::MAX_ATTEMPTS): ?int
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $port = $desiredPort + $i;

            if ($this->isPortAvailable($port)) {
                return $port;
            }
        }

        return null;
    }
}
