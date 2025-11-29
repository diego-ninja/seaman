<?php

// ABOUTME: Bootstraps new Symfony projects using Symfony CLI.
// ABOUTME: Supports multiple project types with appropriate configurations.

declare(strict_types=1);

namespace Seaman\Service;

use Seaman\Enum\ProjectType;
use Symfony\Component\Process\Process;

final readonly class ProjectBootstrapper
{
    /**
     * Get bootstrap command for single-command project types.
     *
     * @param ProjectType $type Project type
     * @param string $name Project name
     * @param string $targetDirectory Target directory
     * @return string Command to execute
     */
    public function getBootstrapCommand(ProjectType $type, string $name, string $targetDirectory): string
    {
        return match ($type) {
            ProjectType::WebApplication => sprintf(
                'cd %s && symfony new %s --webapp',
                escapeshellarg($targetDirectory),
                escapeshellarg($name),
            ),
            ProjectType::Microservice, ProjectType::Skeleton => sprintf(
                'cd %s && symfony new %s --webapp=false',
                escapeshellarg($targetDirectory),
                escapeshellarg($name),
            ),
            ProjectType::ApiPlatform => throw new \InvalidArgumentException(
                'API Platform requires multiple commands. Use getBootstrapCommands() instead.',
            ),
        };
    }

    /**
     * Get bootstrap commands for multi-command project types.
     *
     * @param ProjectType $type Project type
     * @param string $name Project name
     * @param string $targetDirectory Target directory
     * @return list<string> Commands to execute in sequence
     */
    public function getBootstrapCommands(ProjectType $type, string $name, string $targetDirectory): array
    {
        if ($type !== ProjectType::ApiPlatform) {
            return [$this->getBootstrapCommand($type, $name, $targetDirectory)];
        }

        return [
            sprintf(
                'cd %s && symfony new %s --webapp',
                escapeshellarg($targetDirectory),
                escapeshellarg($name),
            ),
            sprintf(
                'cd %s/%s && composer require api',
                escapeshellarg($targetDirectory),
                escapeshellarg($name),
            ),
        ];
    }

    /**
     * Execute bootstrap commands.
     *
     * @param ProjectType $type Project type
     * @param string $name Project name
     * @param string $targetDirectory Target directory
     * @return bool Success status
     */
    public function bootstrap(ProjectType $type, string $name, string $targetDirectory): bool
    {
        $commands = $this->getBootstrapCommands($type, $name, $targetDirectory);

        foreach ($commands as $command) {
            $process = Process::fromShellCommandline($command);
            $process->setTimeout(300); // 5 minutes
            $process->run();

            if (!$process->isSuccessful()) {
                return false;
            }
        }

        return true;
    }
}
