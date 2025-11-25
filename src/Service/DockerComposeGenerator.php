<?php

declare(strict_types=1);

// ABOUTME: Generates docker-compose.yml from configuration.
// ABOUTME: Uses Twig templates to create Docker Compose files.

namespace Seaman\Service;

use Seaman\ValueObject\Configuration;

readonly class DockerComposeGenerator
{
    public function __construct(
        private TemplateRenderer $renderer,
    ) {}

    public function generate(Configuration $config): string
    {
        $context = [
            'php' => $config->php,
            'services' => [
                'enabled' => $config->services->enabled(),
            ],
            'volumes' => $config->volumes,
        ];

        return $this->renderer->render('docker/compose.base.twig', $context);
    }
}
