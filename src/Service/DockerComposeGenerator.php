<?php

declare(strict_types=1);

// ABOUTME: Generates docker-compose.yml from configuration.
// ABOUTME: Uses Twig templates and generates Traefik labels for services.

namespace Seaman\Service;

use Seaman\Enum\Service;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\CustomServiceCollection;
use Seaman\ValueObject\ServiceConfig;
use Symfony\Component\Yaml\Yaml;

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
        $proxyEnabled = $proxy->enabled;

        // Generate Traefik labels for all enabled services (only if proxy enabled)
        $servicesWithLabels = [];
        foreach ($enabledServices as $name => $service) {
            $labels = $proxyEnabled
                ? $this->labelGenerator->generateLabels($service, $proxy)
                : [];
            $servicesWithLabels[$name] = [
                'config' => $service,
                'labels' => $labels,
            ];
        }

        // Generate Traefik labels for app service (only if proxy enabled)
        $appService = $this->createAppServiceConfig($config);
        $appLabels = $proxyEnabled
            ? $this->labelGenerator->generateLabels($appService, $proxy)
            : [];

        $context = [
            'php_version' => $config->php->version->value,
            'app_labels' => $appLabels,
            'services' => [
                'enabled' => $servicesWithLabels,
            ],
            'volumes' => $config->volumes,
            'project_name' => $config->projectName,
            'proxy_enabled' => $proxyEnabled,
        ];

        $baseYaml = $this->renderer->render('docker/compose.base.twig', $context);

        // Merge custom services if present
        if ($config->hasCustomServices()) {
            return $this->mergeCustomServices($baseYaml, $config->customServices);
        }

        return $baseYaml;
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
            environmentVariables: [],
        );
    }

    /**
     * Merge custom services into the generated docker-compose YAML.
     */
    private function mergeCustomServices(string $baseYaml, CustomServiceCollection $customServices): string
    {
        $parsed = Yaml::parse($baseYaml);

        /** @var array<string, mixed> $compose */
        $compose = is_array($parsed) ? $parsed : [];

        if (!isset($compose['services']) || !is_array($compose['services'])) {
            $compose['services'] = [];
        }

        foreach ($customServices->all() as $name => $serviceConfig) {
            // Ensure custom service is connected to the seaman network
            if (!isset($serviceConfig['networks'])) {
                $serviceConfig['networks'] = ['seaman'];
            } elseif (is_array($serviceConfig['networks']) && !in_array('seaman', $serviceConfig['networks'], true)) {
                $serviceConfig['networks'][] = 'seaman';
            }

            $compose['services'][$name] = $serviceConfig;
        }

        return Yaml::dump($compose, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }
}
