<?php

declare(strict_types=1);

// ABOUTME: Generates docker-compose.yml from configuration.
// ABOUTME: Uses Twig templates and generates Traefik labels for services.

namespace Seaman\Service;

use Seaman\Enum\Service;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\ServiceConfig;

readonly class DockerComposeGenerator
{
    public function __construct(
        private TemplateRenderer $renderer,
        private TraefikLabelGenerator $labelGenerator,
    ) {}

    public function generate(Configuration $config): string
    {
        $enabledServices = $config->services->enabled();
        $proxy = $config->proxy();

        // Generate Traefik labels for all enabled services
        $servicesWithLabels = [];
        foreach ($enabledServices as $name => $service) {
            $servicesWithLabels[$name] = [
                'config' => $service,
                'labels' => $this->labelGenerator->generateLabels($service, $proxy),
            ];
        }

        // Generate Traefik labels for app service
        $appService = $this->createAppServiceConfig($config);
        $appLabels = $this->labelGenerator->generateLabels($appService, $proxy);

        $context = [
            'php_version' => $config->php->version->value,
            'app_labels' => $appLabels,
            'services' => [
                'enabled' => $servicesWithLabels,
            ],
            'volumes' => $config->volumes,
            'project_name' => $config->projectName,
        ];

        return $this->renderer->render('docker/compose.base.twig', $context);
    }

    /**
     * Create a ServiceConfig for the app service.
     */
    private function createAppServiceConfig(Configuration $config): ServiceConfig
    {
        return new ServiceConfig(
            name: 'app',
            enabled: true,
            type: Service::App,
            version: $config->php->version->value,
            port: 80,
            additionalPorts: [],
            environmentVariables: []
        );
    }
}
