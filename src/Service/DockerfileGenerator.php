<?php

declare(strict_types=1);

// ABOUTME: Generates Dockerfile from server and PHP configuration.
// ABOUTME: Selects appropriate template based on server type.

namespace Seaman\Service;

use Seaman\ValueObject\ServerConfig;
use Seaman\ValueObject\PhpConfig;

readonly class DockerfileGenerator
{
    public function __construct(
        private TemplateRenderer $renderer,
    ) {}

    public function generate(ServerConfig $server, PhpConfig $php): string
    {
        $template = match ($server->type) {
            'symfony' => 'docker/Dockerfile.symfony.twig',
            'nginx-fpm' => 'docker/Dockerfile.nginx-fpm.twig',
            'frankenphp' => 'docker/Dockerfile.frankenphp.twig',
            default => throw new \InvalidArgumentException("Unknown server type: {$server->type}"),
        };

        $context = [
            'php' => $php,
        ];

        return $this->renderer->render($template, $context);
    }
}
