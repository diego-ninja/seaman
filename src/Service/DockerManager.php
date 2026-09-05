<?php

declare(strict_types=1);

// ABOUTME: Executes docker-compose commands for container management.
// ABOUTME: Provides methods for starting, stopping, and managing Docker containers.

namespace Seaman\Service;

use Seaman\UI\Widget\Spinner\SpinnerFactory;
use Seaman\ValueObject\LogOptions;
use Seaman\ValueObject\ProcessResult;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

readonly class DockerManager
{
    /** @var list<string> */
    private const array COMPOSE_COMMAND = ['docker', 'compose'];

    private const int TIMEOUT_EXIT_CODE = 124;

    private ComposeFileLocator $composeFileLocator;

    public function __construct(
        private string $projectPath,
    ) {
        $this->composeFileLocator = new ComposeFileLocator($this->projectPath);
    }

    /**
     * Starts Docker containers.
     *
     * @param string|null $service Optional service name to start only that service
     * @return ProcessResult The result of the start operation
     * @throws \RuntimeException When docker-compose.yml does not exist
     * @throws \Exception
     */
    public function start(?string $service = null): ProcessResult
    {
        $this->ensureComposeFileExists();
        $this->ensureTraefikPingEnabled();

        $command = $this->composeCommand('up', '-d');

        if ($service !== null) {
            $command[] = $service;
        }

        return $this->runProcess($command, 'Starting seaman services...');
    }

    /**
     * Stops Docker containers.
     *
     * @param string|null $service Optional service name to stop only that service
     * @return ProcessResult The result of the stop operation
     * @throws \RuntimeException|\Exception When docker-compose.yml does not exist
     */
    public function stop(?string $service = null): ProcessResult
    {
        $this->ensureComposeFileExists();

        $command = $this->composeCommand('stop');

        if ($service !== null) {
            $command[] = $service;
        }

        return $this->runProcess($command, 'Stopping seaman services...');
    }

    /**
     * Restarts Docker containers.
     *
     * @param string|null $service Optional service name to restart only that service
     * @return ProcessResult The result of the restart operation
     * @throws \RuntimeException|\Exception When docker-compose.yml does not exist
     */
    public function restart(?string $service = null): ProcessResult
    {
        $this->ensureComposeFileExists();
        $this->ensureTraefikPingEnabled();

        $command = $this->composeCommand('restart');

        if ($service !== null) {
            $command[] = $service;
        }

        return $this->runProcess($command, 'Restarting seaman services...');
    }

    /**
     * Executes a command inside a service container.
     *
     * @param string $service The service name
     * @param list<string> $command The command and arguments to execute
     * @return ProcessResult The result of the command execution
     * @throws \RuntimeException|\Exception When docker-compose.yml does not exist
     */
    public function executeInService(
        string $service,
        array $command,
        ?string $message = null,
        ?float $timeout = null,
    ): ProcessResult {
        $this->ensureComposeFileExists();

        $fullCommand = $this->composeCommand('exec', '-T', $service);
        $fullCommand = array_merge($fullCommand, $command);

        return $this->runProcess($fullCommand, $message, $timeout);
    }

    /**
     * Executes a command inside a service container with stdin input.
     *
     * @param string $service The service name
     * @param list<string> $command The command and arguments to execute
     * @param string $stdin The input to pipe to stdin
     * @param string|null $message Message to display
     * @return ProcessResult The result of the command execution
     * @throws \RuntimeException When docker-compose.yml does not exist
     * @throws \Exception
     */
    public function executeInServiceWithStdin(
        string $service,
        array $command,
        string $stdin,
        ?string $message = null,
    ): ProcessResult {
        $this->ensureComposeFileExists();

        $fullCommand = $this->composeCommand('exec', '-T', $service);
        $fullCommand = array_merge($fullCommand, $command);

        $process = new Process($fullCommand);
        $process->setInput($stdin);
        $process->setTimeout(null);

        if ($message !== null) {
            return $this->runProcessWithSpinner($process, $message);
        }

        $process->run();

        return new ProcessResult(
            exitCode: $process->getExitCode() ?? 1,
            output: $process->getOutput(),
            errorOutput: $process->getErrorOutput(),
        );
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

        $fullCommand = $this->composeCommand('exec', $service);
        $fullCommand = array_merge($fullCommand, $command);

        $process = new Process($fullCommand);
        $process->setTty(Process::isTtySupported());
        $process->setTimeout(null);

        return $process->run();
    }

    /**
     * Executes a command with full passthrough (TTY if supported, streaming otherwise).
     *
     * This method provides real-time output streaming with ANSI support,
     * making it ideal for commands like composer, phpunit, etc.
     *
     * @param string $service The service name
     * @param list<string> $command The command and arguments to execute
     * @return int The exit code of the command
     * @throws \RuntimeException When docker-compose.yml does not exist
     */
    public function executePassthrough(string $service, array $command): int
    {
        $this->ensureComposeFileExists();

        $fullCommand = $this->composeCommand('exec');

        // Use TTY if supported for full terminal emulation
        if (Process::isTtySupported()) {
            $fullCommand[] = $service;
            $fullCommand = array_merge($fullCommand, $command);

            $process = new Process($fullCommand);
            $process->setTty(true);
            $process->setTimeout(null);

            return $process->run();
        }

        // Fallback: stream output in real-time without TTY
        $fullCommand[] = '-T';
        $fullCommand[] = $service;
        $fullCommand = array_merge($fullCommand, $command);

        $process = new Process($fullCommand);
        $process->setTimeout(null);

        return $process->run(function (string $type, string $buffer): void {
            if ($type === Process::OUT) {
                fwrite(STDOUT, $buffer);
            } else {
                fwrite(STDERR, $buffer);
            }
        });
    }

    /**
     * Shows logs for a service.
     *
     * @param string $service The service name
     * @param LogOptions $options Log viewing options
     * @return ProcessResult The result containing the logs
     * @throws \RuntimeException When docker-compose.yml does not exist
     * @throws \Exception
     */
    public function logs(string $service, LogOptions $options): ProcessResult
    {
        $this->ensureComposeFileExists();

        $command = $this->composeCommand('logs');

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

        return $this->runProcess($command, null, $timeout, $options->follow);
    }

    /**
     * Gets the status of all Docker containers.
     *
     * @return list<array<string, string>> A list of service statuses
     * @throws \RuntimeException|\Exception When docker-compose.yml does not exist or output is invalid
     */
    public function status(): array
    {
        $this->ensureComposeFileExists();

        $command = $this->composeCommand('ps', '--format', 'json');

        $result = $this->runProcess($command);

        if (!$result->isSuccessful()) {
            return [];
        }

        // Handle empty output (no containers)
        if (trim($result->output) === '') {
            return [];
        }

        $serviceInfos = [];
        foreach (preg_split('/\R/', trim($result->output)) ?: [] as $json) {
            /** @var array<string, string> $data */
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $serviceInfos[] = $data;
        }

        return $serviceInfos;
    }

    /**
     * Rebuilds Docker images.
     *
     * @param string|null $service Optional service name to rebuild only that service
     * @return ProcessResult The result of the rebuild operation
     * @throws \RuntimeException|\Exception When docker-compose.yml does not exist
     */
    public function rebuild(?string $service = null): ProcessResult
    {
        $this->ensureComposeFileExists();

        $command = $this->composeCommand('build', '--no-cache');

        if ($service !== null) {
            $command[] = $service;
        }

        return $this->runProcess($command, 'Rebuilding seaman stack...', 300.0); // 5 minutes for builds
    }

    /**
     * Stops and removes all Docker containers and networks without deleting volumes.
     *
     * @return ProcessResult The result of the down operation
     * @throws \RuntimeException When docker-compose.yml does not exist
     * @throws \Exception
     */
    public function down(): ProcessResult
    {
        $this->ensureComposeFileExists();

        $command = $this->composeCommand('down', '--remove-orphans');

        return $this->runProcess($command, 'Stopping seaman stack...', 120.0);
    }

    /**
     * Destroys all Docker containers, volumes, and networks.
     *
     * @return ProcessResult The result of the destroy operation
     * @throws \RuntimeException When docker-compose.yml does not exist
     * @throws \Exception
     */
    public function destroy(): ProcessResult
    {
        $this->ensureComposeFileExists();

        $command = $this->composeCommand('down', '-v', '--remove-orphans');

        return $this->runProcess($command, 'Destroying seaman stack...', 300.0);
    }

    /**
     * Ensures the docker-compose.yml file exists.
     *
     * @throws \RuntimeException When the file does not exist
     */
    private function ensureComposeFileExists(): void
    {
        $this->composeFileLocator->require();
    }

    private function ensureTraefikPingEnabled(): void
    {
        $configPath = $this->projectPath . '/.seaman/traefik/traefik.yml';
        if (!is_file($configPath)) {
            return;
        }

        $config = file_get_contents($configPath);
        if ($config === false) {
            return;
        }

        $documentEndMarker = '';
        if (preg_match(
            '/^\.{3}[ \t]*(?:#[^\r\n]*)?(?:\R[ \t]*(?:#[^\r\n]*)?)*\z/m',
            $config,
            $matches,
            PREG_OFFSET_CAPTURE,
        ) === 1) {
            $markerOffset = $matches[0][1];
            $documentEndMarker = substr($config, $markerOffset);
            $config = substr($config, 0, $markerOffset);
        }

        try {
            $parsedConfig = Yaml::parse($config);
        } catch (ParseException) {
            return;
        }

        if (is_array($parsedConfig) && array_key_exists('ping', $parsedConfig)) {
            return;
        }

        if ($parsedConfig !== null && !is_array($parsedConfig)) {
            return;
        }

        $separator = $config === '' || str_ends_with($config, "\n") ? '' : "\n";
        file_put_contents($configPath, $config . $separator . "\nping: {}\n" . $documentEndMarker);
    }

    /**
     * @return list<string>
     */
    private function composeCommand(string ...$arguments): array
    {
        return array_values(array_merge(
            self::COMPOSE_COMMAND,
            ['-f', $this->composeFileLocator->require()],
            $arguments,
        ));
    }

    /**
     * Runs a process and returns the result.
     *
     * @param list<string> $command The command to execute
     * @param string|null $message Message to display
     * @param float|null $timeout Process timeout in seconds (null for no timeout)
     * @return ProcessResult The process execution result
     * @throws \Exception
     */
    private function runProcess(
        array $command,
        ?string $message = null,
        ?float $timeout = 60.0,
        bool $allowTimeout = false,
    ): ProcessResult {
        $process = new Process($command);
        $process->setTimeout($timeout);

        try {
            $message === null ? $process->run() : SpinnerFactory::for($process, $message);
        } catch (ProcessTimedOutException) {
            return new ProcessResult(
                exitCode: $allowTimeout ? 0 : self::TIMEOUT_EXIT_CODE,
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

    /**
     * Runs a process with a spinner and returns the result.
     *
     * @param Process $process The process to execute
     * @param string $message Message to display
     * @return ProcessResult The process execution result
     * @throws \Exception
     */
    private function runProcessWithSpinner(Process $process, string $message): ProcessResult
    {
        try {
            SpinnerFactory::for($process, $message);
        } catch (ProcessTimedOutException) {
            return new ProcessResult(
                exitCode: self::TIMEOUT_EXIT_CODE,
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
