<?php

declare(strict_types=1);

// ABOUTME: Real command executor using Symfony Process.
// ABOUTME: Executes actual shell commands.

namespace Seaman\Service\Process;

use Seaman\Contract\CommandExecutor;
use Seaman\ValueObject\ProcessResult;
use Symfony\Component\Process\Process;

final readonly class RealCommandExecutor implements CommandExecutor
{
    public function execute(array $command): ProcessResult
    {
        $process = new Process($command);
        $process->run();

        return new ProcessResult(
            exitCode: $process->getExitCode() ?? 1,
            output: $process->getOutput(),
            errorOutput: $process->getErrorOutput(),
        );
    }
}
