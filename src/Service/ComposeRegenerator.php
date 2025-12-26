<?php

declare(strict_types=1);

// ABOUTME: Regenerates docker-compose.yml and optionally restarts services.
// ABOUTME: Injectable service for managing docker-compose regeneration and restart operations.

namespace Seaman\Service;

use Seaman\Service\Generator\DockerComposeGenerator;
use Seaman\UI\Prompts;
use Seaman\UI\Terminal;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\ProcessResult;

final readonly class ComposeRegenerator
{
    public function __construct(
        private DockerComposeGenerator $composeGenerator,
        private DockerManager $dockerManager,
    ) {}

    public function regenerate(Configuration $config, string $projectRoot): void
    {
        $composeYaml = $this->composeGenerator->generate($config);
        file_put_contents($projectRoot . '/docker-compose.yml', $composeYaml);

        Terminal::success('Services updated successfully.');
    }

    public function restartIfConfirmed(): ProcessResult
    {
        if (!Prompts::confirm(label: 'Restart seaman stack with new services?')) {
            return new ProcessResult(0, '', '');
        }

        $downResult = $this->dockerManager->down();
        if (!$downResult->isSuccessful()) {
            Terminal::error('Failed to stop services');
            Terminal::output()->writeln($downResult->errorOutput);
            return $downResult;
        }

        $startResult = $this->dockerManager->start();
        if (!$startResult->isSuccessful()) {
            Terminal::error('Failed to start services');
            Terminal::output()->writeln($startResult->errorOutput);
            return $startResult;
        }

        Terminal::success('Stack restarted successfully.');
        return $startResult;
    }
}
