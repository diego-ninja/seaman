<?php

declare(strict_types=1);

// ABOUTME: Builds and tags Docker images from Dockerfile.
// ABOUTME: Encapsulates Docker build command execution.

namespace Seaman\Service\Builder;

use Seaman\Enum\PhpVersion;
use Seaman\Enum\ServerType;
use Seaman\UI\Widget\Spinner\SpinnerFactory;
use Seaman\ValueObject\ProcessResult;
use Symfony\Component\Process\Process;

readonly class DockerImageBuilder
{
    public function __construct(
        private string $projectRoot,
        private PhpVersion $phpVersion,
        private ServerType $serverType,
    ) {}

    /**
     * Builds Docker image and tags it.
     *
     * @param bool $noCache When true, builds without using Docker cache
     * @return ProcessResult The result of the build operation
     * @throws \Exception
     */
    public function build(bool $noCache = false): ProcessResult
    {
        $wwwgroup = (string) posix_getgid();
        $image = sprintf(
            'seaman/seaman-php%s-%s:latest',
            $this->phpVersion->value,
            $this->serverType->value,
        );

        $command = [
            'docker',
            'build',
            '-t',
            $image,
            '-f',
            '.seaman/Dockerfile',
            '--build-arg',
            "WWWGROUP={$wwwgroup}",
            '--build-arg',
            "PHP_VERSION={$this->phpVersion->value}",
        ];

        if ($noCache) {
            $command[] = '--no-cache';
        }

        $command[] = '.';

        $process = new Process($command, $this->projectRoot, timeout: 300.0);
        SpinnerFactory::for(
            callable: $process,
            message: 'Building Docker image: ' . $image,
        );

        return new ProcessResult(
            exitCode: $process->getExitCode() ?? 1,
            output: $process->getOutput(),
            errorOutput: $process->getErrorOutput(),
        );
    }
}
