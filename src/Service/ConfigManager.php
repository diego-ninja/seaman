<?php

declare(strict_types=1);

// ABOUTME: Manages seaman.yaml configuration loading and saving.
// ABOUTME: Handles YAML parsing and Configuration object creation.

namespace Seaman\Service;

use Exception;
use RuntimeException;
use Seaman\Enum\ProjectType;
use Seaman\Service\ConfigParser\CustomServiceParser;
use Seaman\Service\ConfigParser\PhpConfigParser;
use Seaman\Service\ConfigParser\ProxyConfigParser;
use Seaman\Service\ConfigParser\ServiceConfigParser;
use Seaman\Service\ConfigParser\VolumeConfigParser;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\ValueObject\Configuration;
use Symfony\Component\Yaml\Yaml;

readonly class ConfigManager
{
    private PhpConfigParser $phpParser;
    private ServiceConfigParser $serviceParser;
    private VolumeConfigParser $volumeParser;
    private ProxyConfigParser $proxyParser;
    private CustomServiceParser $customServiceParser;

    public function __construct(
        private string $projectRoot,
        private ServiceRegistry $serviceRegistry,
        private ConfigurationValidator $validator,
    ) {
        $this->phpParser = new PhpConfigParser();
        $this->serviceParser = new ServiceConfigParser();
        $this->volumeParser = new VolumeConfigParser();
        $this->proxyParser = new ProxyConfigParser();
        $this->customServiceParser = new CustomServiceParser();
    }

    public function load(): Configuration
    {
        $yamlPath = $this->projectRoot . '/.seaman/seaman.yaml';

        if (!file_exists($yamlPath)) {
            throw new RuntimeException('seaman.yaml not found at ' . $yamlPath);
        }

        $content = file_get_contents($yamlPath);
        if ($content === false) {
            throw new RuntimeException('Failed to read seaman.yaml');
        }

        try {
            $data = Yaml::parse($content);
        } catch (Exception $e) {
            throw new RuntimeException('Failed to parse YAML: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($data)) {
            throw new RuntimeException('Invalid YAML structure');
        }

        /** @var array<string, mixed> $data */

        // Validate configuration structure
        $this->validator->validate($data);

        return $this->parseConfiguration($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function parseConfiguration(array $data): Configuration
    {
        $projectName = $data['project_name'] ?? '';
        if (!is_string($projectName)) {
            throw new RuntimeException('project_name must be a string');
        }

        $version = $data['version'] ?? '1.0';
        if (!is_string($version)) {
            $version = '1.0';
        }

        $projectTypeString = $data['project_type'] ?? null;
        $projectType = is_string($projectTypeString) ? ProjectType::tryFrom($projectTypeString) : null;
        $projectType = $projectType ?? ProjectType::Existing;

        return new Configuration(
            projectName: $projectName,
            version: $version,
            php: $this->phpParser->parse($data),
            services: $this->serviceParser->parse($data),
            volumes: $this->volumeParser->parse($data),
            projectType: $projectType,
            proxy: $this->proxyParser->parse($data, $projectName),
            customServices: $this->customServiceParser->parse($data),
        );
    }

    public function save(Configuration $config): void
    {
        $data = [
            'project_name' => $config->projectName,
            'version' => $config->version,
            'project_type' => $config->projectType->value,
            'php' => [
                'version' => $config->php->version->value,
                'xdebug' => [
                    'enabled' => $config->php->xdebug->enabled,
                    'ide_key' => $config->php->xdebug->ideKey,
                    'client_host' => $config->php->xdebug->clientHost,
                ],
            ],
            'services' => [],
            'volumes' => [
                'persist' => $config->volumes->persist,
            ],
        ];

        foreach ($config->services->all() as $name => $service) {
            $data['services'][$name] = [
                'enabled' => $service->enabled,
                'type' => $service->type->value,
                'version' => $service->version,
                'port' => $service->port,
            ];

            if (!empty($service->additionalPorts)) {
                $data['services'][$name]['additional_ports'] = $service->additionalPorts;
            }

            if (!empty($service->environmentVariables)) {
                $data['services'][$name]['environment'] = $service->environmentVariables;
            }
        }

        // Add proxy configuration
        $proxy = $config->proxy();
        $data['proxy'] = [
            'enabled' => $proxy->enabled,
            'domain_prefix' => $proxy->domainPrefix,
            'cert_resolver' => $proxy->certResolver,
            'dashboard' => $proxy->dashboard,
        ];

        // Add custom services if present
        if ($config->hasCustomServices()) {
            $data['custom_services'] = $config->customServices->all();
        }

        $yamlContent = Yaml::dump($data, 4, 2);

        // Ensure .seaman directory exists
        $seamanDir = $this->projectRoot . '/.seaman';
        if (!is_dir($seamanDir)) {
            mkdir($seamanDir, 0755, true);
        }

        $yamlPath = $seamanDir . '/seaman.yaml';

        if (file_put_contents($yamlPath, $yamlContent) === false) {
            throw new RuntimeException('Failed to write seaman.yaml');
        }

        $this->generateEnv($config);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public function merge(Configuration $base, array $overrides): Configuration
    {
        $version = $overrides['version'] ?? $base->version;
        if (!is_string($version)) {
            $version = $base->version;
        }

        $projectName = $overrides['project_name'] ?? $base->projectName;
        if (!is_string($projectName)) {
            $projectName = $base->projectName;
        }

        $projectTypeString = $overrides['project_type'] ?? null;
        $projectType = is_string($projectTypeString) ? ProjectType::tryFrom($projectTypeString) : null;
        $projectType = $projectType ?? $base->projectType;

        return new Configuration(
            projectName: $projectName,
            version: $version,
            php: $this->phpParser->merge($overrides, $base->php),
            services: $this->serviceParser->merge($overrides, $base->services->all()),
            volumes: $this->volumeParser->merge($overrides, $base->volumes->persist),
            projectType: $projectType,
        );
    }

    private function generateEnv(Configuration $config): void
    {
        $lines = [
            '# Application configuration',
            'APP_PORT=8000',
            '',
            '# PHP configuration',
            'PHP_VERSION=' . $config->php->version->value,
            '',
            '# Xdebug configuration',
            'XDEBUG_MODE=' . ($config->php->xdebug->enabled ? 'debug' : 'off'),
            '',
        ];

        foreach ($config->services->all() as $name => $serviceConfig) {
            if (!$serviceConfig->enabled) {
                continue;
            }

            try {
                $service = $this->serviceRegistry->get($serviceConfig->type);
                $envVars = $service->getEnvVariables($serviceConfig);

                $lines[] = '# ' . ucfirst($name) . ' configuration';
                foreach ($envVars as $key => $value) {
                    $lines[] = $key . '=' . $value;
                }
                $lines[] = '';
            } catch (\ValueError|\InvalidArgumentException $e) {
                continue;
            }
        }

        $envContent = implode("\n", $lines);
        $envPath = $this->projectRoot . '/.env';

        if (file_put_contents($envPath, $envContent) === false) {
            throw new RuntimeException('Failed to write .env');
        }
    }
}
