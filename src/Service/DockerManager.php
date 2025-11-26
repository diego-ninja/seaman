<?php

declare(strict_types=1);

// ABOUTME: Executes docker-compose commands for container management.
// ABOUTME: Provides methods for starting, stopping, and managing Docker containers.

namespace Seaman\Service;

use Seaman\ValueObject\LogOptions;
use Seaman\ValueObject\ProcessResult;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

readonly class DockerManager
{
    private string $composeFile;

    public function __construct(
        private string $projectPath,
    ) {
        $this->composeFile = $this->projectPath . '/docker-compose.yml';
    }

    /**
     * Starts Docker containers.
     *
     * @param string|null $service Optional service name to start only that service
     * @return ProcessResult The result of the start operation
     * @throws \RuntimeException When docker-compose.yml does not exist
     */
    public function start(?string $service = null): ProcessResult
    {
        $this->ensureComposeFileExists();

        $command = ['docker-compose', '-f', $this->composeFile, 'up', '-d'];

        if ($service !== null) {
            $command[] = $service;
        }

        return $this->runProcess($command);
    }

    /**
     * Stops Docker containers.
     *
     * @param string|null $service Optional service name to stop only that service
     * @return ProcessResult The result of the stop operation
     * @throws \RuntimeException When docker-compose.yml does not exist
     */
    public function stop(?string $service = null): ProcessResult
    {
        $this->ensureComposeFileExists();

        $command = ['docker-compose', '-f', $this->composeFile, 'stop'];

        if ($service !== null) {
            $command[] = $service;
        }

        return $this->runProcess($command);
    }

    /**
     * Restarts Docker containers.
     *
     * @param string|null $service Optional service name to restart only that service
     * @return ProcessResult The result of the restart operation
     * @throws \RuntimeException When docker-compose.yml does not exist
     */
    public function restart(?string $service = null): ProcessResult
    {
        $this->ensureComposeFileExists();

        $command = ['docker-compose', '-f', $this->composeFile, 'restart'];

        if ($service !== null) {
            $command[] = $service;
        }

        return $this->runProcess($command);
    }

    /**
     * Executes a command inside a service container.
     *
     * @param string $service The service name
     * @param list<string> $command The command and arguments to execute
     * @return ProcessResult The result of the command execution
     * @throws \RuntimeException When docker-compose.yml does not exist
     */
    public function executeInService(string $service, array $command): ProcessResult
    {
        $this->ensureComposeFileExists();

        $fullCommand = ['docker-compose', '-f', $this->composeFile, 'exec', '-T', $service];
        $fullCommand = array_merge($fullCommand, $command);

        return $this->runProcess($fullCommand);
    }

    /**
     * Executes an interactive command inside a service container.
     *
     * @param string $service The service name
     * @param list<string> $command The command and arguments to execute
     * @return int The exit code of the command
     * @throws \RuntimeException When docker-compose.yml does not exist
     */
    public function executeInteractive(string $service, array $command): int
    {
        $this->ensureComposeFileExists();

        $fullCommand = ['docker-compose', '-f', $this->composeFile, 'exec', $service];
        $fullCommand = array_merge($fullCommand, $command);

        $process = new Process($fullCommand);
        $process->setTty(Process::isTtySupported());
        $process->setTimeout(null);

        return $process->run();
    }

    /**
     * Shows logs for a service.
     *
     * @param string $service The service name
     * @param LogOptions $options Log viewing options
     * @return ProcessResult The result containing the logs
     * @throws \RuntimeException When docker-compose.yml does not exist
     */
    public function logs(string $service, LogOptions $options): ProcessResult
    {
        $this->ensureComposeFileExists();

        $command = ['docker-compose', '-f', $this->composeFile, 'logs'];

        if ($options->follow) {
            $command[] = '--follow';
        }

        if ($options->tail !== null) {
            $command[] = '--tail';
            $command[] = (string) $options->tail;
        }

        if ($options->since !== null) {
            $command[] = '--since';
            $command[] = $options->since;
        }

        $command[] = $service;

        // Use shorter timeout when following logs to prevent hanging
        $timeout = $options->follow ? 2.0 : 60.0;

        return $this->runProcess($command, $timeout);
    }

    /**
     * Gets the status of all Docker containers.
     *
     * @return list<array<string, mixed>> A list of service statuses
     * @throws \RuntimeException When docker-compose.yml does not exist or output is invalid
     */
    public function status(): array
    {
        $this->ensureComposeFileExists();

        $command = ['docker-compose', '-f', $this->composeFile, 'ps', '--format', 'json'];

        $result = $this->runProcess($command);

        if (!$result->isSuccessful()) {
            return [];
        }

        // Handle empty output (no containers)
        if (trim($result->output) === '') {
            return [];
        }

        try {
            /** @var array<array<string, mixed>> $decoded */
            $decoded = json_decode($result->output, true, 512, JSON_THROW_ON_ERROR);
            return array_values($decoded);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Failed to parse docker-compose status JSON: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Rebuilds Docker images.
     *
     * @param string|null $service Optional service name to rebuild only that service
     * @return ProcessResult The result of the rebuild operation
     * @throws \RuntimeException When docker-compose.yml does not exist
     */
    public function rebuild(?string $service = null): ProcessResult
    {
        $this->ensureComposeFileExists();

        $command = ['docker-compose', '-f', $this->composeFile, 'build', '--no-cache'];

        if ($service !== null) {
            $command[] = $service;
        }

        return $this->runProcess($command, 300.0); // 5 minutes for builds
    }

    /**
     * Destroys all Docker containers, volumes, and networks.
     *
     * @return ProcessResult The result of the destroy operation
     * @throws \RuntimeException When docker-compose.yml does not exist
     */
    public function destroy(): ProcessResult
    {
        $this->ensureComposeFileExists();

        $command = ['docker-compose', '-f', $this->composeFile, 'down', '-v', '--remove-orphans'];

        return $this->runProcess($command);
    }

    /**
     * Ensures the docker-compose.yml file exists.
     *
     * @throws \RuntimeException When the file does not exist
     */
    private function ensureComposeFileExists(): void
    {
        if (!file_exists($this->composeFile)) {
            throw new \RuntimeException(
                "Docker Compose file not found at: {$this->composeFile}",
            );
        }
    }

    /**
     * Runs a process and returns the result.
     *
     * @param list<string> $command The command to execute
     * @param float|null $timeout Process timeout in seconds (null for no timeout)
     * @return ProcessResult The process execution result
     */
    private function runProcess(array $command, ?float $timeout = 60.0): ProcessResult
    {
        $process = new Process($command);
        $process->setTimeout($timeout);

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            // Timeout is expected for commands like logs --follow
            // Return what we got before timeout
            return new ProcessResult(
                exitCode: 0,
                output: $process->getOutput(),
                errorOutput: $process->getErrorOutput(),
            );
        }

        return new ProcessResult(
            exitCode: $process->getExitCode() ?? 1,
            output: $process->getOutput(),
            errorOutput: $process->getErrorOutput(),
        );
    }
}
