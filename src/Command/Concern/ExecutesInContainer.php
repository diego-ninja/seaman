<?php

declare(strict_types=1);

// ABOUTME: Provides container execution functionality for commands.
// ABOUTME: Handles executing commands in Docker services with consistent output handling.

namespace Seaman\Command\Concern;

use Seaman\Service\DockerManager;
use Seaman\UI\Terminal;
use Symfony\Component\Console\Command\Command;

trait ExecutesInContainer
{
    /**
     * Executes a command in a Docker service and handles output.
     *
     * @param list<string> $command The command to execute
     * @param string $service The service name (default: 'app')
     * @return int Command::SUCCESS or Command::FAILURE
     * @throws \Exception
     */
    protected function executeInContainer(array $command, string $service = 'app'): int
    {
        $projectRoot = (string) getcwd();
        $manager = new DockerManager($projectRoot);

        $result = $manager->executeInService($service, $command);
        Terminal::output()->writeln($result->output);

        if (!$result->isSuccessful()) {
            Terminal::error($result->output);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
