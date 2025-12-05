<?php

declare(strict_types=1);

// ABOUTME: Provides docker-compose regeneration functionality for commands.
// ABOUTME: Handles template rendering and file writing.

namespace Seaman\Command\Concern;

use Seaman\Service\Generator\DockerComposeGenerator;
use Seaman\Service\Generator\TraefikLabelGenerator;
use Seaman\Service\TemplateRenderer;
use Seaman\ValueObject\Configuration;

trait RegeneratesDockerCompose
{
    /**
     * Regenerates the docker-compose.yml file from configuration.
     */
    protected function regenerateDockerCompose(Configuration $config, string $projectRoot): void
    {
        $templateDir = __DIR__ . '/../../Template';
        $renderer = new TemplateRenderer($templateDir);
        $labelGenerator = new TraefikLabelGenerator();
        $composeGenerator = new DockerComposeGenerator($renderer, $labelGenerator);
        $composeYaml = $composeGenerator->generate($config);
        file_put_contents($projectRoot . '/docker-compose.yml', $composeYaml);
    }
}
