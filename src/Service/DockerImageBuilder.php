<?php

declare(strict_types=1);

// ABOUTME: Builds and tags Docker images from Dockerfile.
// ABOUTME: Encapsulates Docker build command execution.

namespace Seaman\Service;

use Seaman\ValueObject\ProcessResult;
use Symfony\Component\Process\Process;

readonly class DockerImageBuilder
{
    public function __construct(
        private string $projectRoot,
    ) {}

    /**
     * Builds Docker image and tags it as seaman/seaman:latest.
     *
     * @return ProcessResult The result of the build operation
     */
    public function build(): ProcessResult
    {
        $wwwgroup = (string) posix_getgid();

        $command = [
            'docker',
            'build',
            '-t',
            'seaman/seaman:latest',
            '-f',
            '.seaman/Dockerfile',
            '--build-arg',
            "WWWGROUP={$wwwgroup}",
            '.',
        ];

        $process = new Process($command, $this->projectRoot, timeout: 300.0);
        $process->run();

        return new ProcessResult(
            exitCode: $process->getExitCode() ?? 1,
            output: $process->getOutput(),
            errorOutput: $process->getErrorOutput(),
        );
    }
}
