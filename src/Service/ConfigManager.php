<?php

declare(strict_types=1);

// ABOUTME: Manages seaman.yaml configuration loading and saving.
// ABOUTME: Handles YAML parsing and Configuration object creation.

namespace Seaman\Service;

use Exception;
use RuntimeException;
use Seaman\Enum\PhpVersion;
use Seaman\Enum\ProjectType;
use Seaman\Enum\Service;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\CustomServiceCollection;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\ProxyConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\VolumeConfig;
use Seaman\ValueObject\XdebugConfig;
use Symfony\Component\Yaml\Yaml;

readonly class ConfigManager
{
    public function __construct(
        private string $projectRoot,
        private ServiceRegistry $serviceRegistry,
        private ConfigurationValidator $validator,
    ) {}

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

        $phpData = $data['php'] ?? [];
        if (!is_array($phpData)) {
            throw new RuntimeException('Invalid PHP configuration');
        }

        $xdebugData = $phpData['xdebug'] ?? [];
        if (!is_array($xdebugData)) {
            throw new RuntimeException('Invalid xdebug configuration');
        }

        $xdebugEnabled = $xdebugData['enabled'] ?? false;
        if (!is_bool($xdebugEnabled)) {
            throw new RuntimeException('Xdebug enabled must be a boolean');
        }

        $xdebugIdeKey = $xdebugData['ide_key'] ?? 'PHPSTORM';
        if (!is_string($xdebugIdeKey)) {
            throw new RuntimeException('Xdebug IDE key must be a string');
        }

        $xdebugClientHost = $xdebugData['client_host'] ?? 'host.docker.internal';
        if (!is_string($xdebugClientHost)) {
            throw new RuntimeException('Xdebug client host must be a string');
        }

        $xdebug = new XdebugConfig(
            enabled: $xdebugEnabled,
            ideKey: $xdebugIdeKey,
            clientHost: $xdebugClientHost,
        );

        $versionString = $phpData['version'] ?? null;
        $phpVersion = is_string($versionString) ? PhpVersion::tryFrom($versionString) : null;
        $phpVersion = $phpVersion ?? PhpVersion::Php84;

        $php = new PhpConfig(
            version: $phpVersion,
            xdebug: $xdebug,
        );

        $servicesData = $data['services'] ?? [];
        if (!is_array($servicesData)) {
            throw new RuntimeException('Invalid services configuration');
        }

        /** @var array<string, ServiceConfig> $services */
        $services = [];
        foreach ($servicesData as $name => $serviceData) {
            if (!is_string($name) || !is_array($serviceData)) {
                continue;
            }

            $enabled = $serviceData['enabled'] ?? false;
            if (!is_bool($enabled)) {
                $enabled = false;
            }

            $type = $serviceData['type'] ?? $name;
            if (!is_string($type)) {
                $type = $name;
            }

            $version = $serviceData['version'] ?? 'latest';
            if (!is_string($version)) {
                $version = 'latest';
            }

            $port = $serviceData['port'] ?? 0;
            if (!is_int($port)) {
                $port = 0;
            }

            $additionalPorts = $serviceData['additional_ports'] ?? [];
            if (!is_array($additionalPorts)) {
                $additionalPorts = [];
            }
            /** @var list<int> $portsList */
            $portsList = [];
            foreach ($additionalPorts as $p) {
                if (is_int($p)) {
                    $portsList[] = $p;
                }
            }

            $environmentVariables = $serviceData['environment'] ?? [];
            if (!is_array($environmentVariables)) {
                $environmentVariables = [];
            }
            /** @var array<string, string> $envVars */
            $envVars = array_filter($environmentVariables, function ($value, $key) {
                return is_string($key) && is_string($value);
            }, ARRAY_FILTER_USE_BOTH);

            $services[$name] = new ServiceConfig(
                name: $name,
                enabled: $enabled,
                type: Service::from($type),
                version: $version,
                port: $port,
                additionalPorts: $portsList,
                environmentVariables: $envVars,
            );
        }

        $volumesData = $data['volumes'] ?? [];
        if (!is_array($volumesData)) {
            throw new RuntimeException('Invalid volumes configuration');
        }

        $persistData = $volumesData['persist'] ?? [];
        if (!is_array($persistData)) {
            throw new RuntimeException('Invalid persist configuration');
        }

        /** @var list<string> $persistList */
        $persistList = [];
        foreach ($persistData as $volume) {
            if (is_string($volume)) {
                $persistList[] = $volume;
            }
        }

        $volumes = new VolumeConfig(
            persist: $persistList,
        );

        $version = $data['version'] ?? '1.0';
        if (!is_string($version)) {
            $version = '1.0';
        }

        $projectTypeString = $data['project_type'] ?? null;
        $projectType = is_string($projectTypeString) ? ProjectType::tryFrom($projectTypeString) : null;
        $projectType = $projectType ?? ProjectType::Existing;

        // Parse proxy configuration
        $proxyConfig = $this->parseProxyConfig($data, $projectName);

        // Parse custom services
        $customServices = $this->parseCustomServices($data);

        return new Configuration(
            projectName: $projectName,
            version: $version,
            php: $php,
            services: new ServiceCollection($services),
            volumes: $volumes,
            projectType: $projectType,
            proxy: $proxyConfig,
            customServices: $customServices,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function parseProxyConfig(array $data, string $projectName): ProxyConfig
    {
        $proxyData = $data['proxy'] ?? [];
        if (!is_array($proxyData)) {
            return ProxyConfig::default($projectName);
        }

        $enabled = $proxyData['enabled'] ?? true;
        if (!is_bool($enabled)) {
            $enabled = true;
        }

        $domainPrefix = $proxyData['domain_prefix'] ?? $projectName;
        if (!is_string($domainPrefix)) {
            $domainPrefix = $projectName;
        }

        $certResolver = $proxyData['cert_resolver'] ?? 'selfsigned';
        if (!is_string($certResolver)) {
            $certResolver = 'selfsigned';
        }

        $dashboard = $proxyData['dashboard'] ?? true;
        if (!is_bool($dashboard)) {
            $dashboard = true;
        }

        return new ProxyConfig(
            enabled: $enabled,
            domainPrefix: $domainPrefix,
            certResolver: $certResolver,
            dashboard: $dashboard,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function parseCustomServices(array $data): CustomServiceCollection
    {
        $customData = $data['custom_services'] ?? [];
        if (!is_array($customData)) {
            return new CustomServiceCollection();
        }

        /** @var array<string, array<string, mixed>> $validCustomServices */
        $validCustomServices = [];
        foreach ($customData as $name => $config) {
            if (is_string($name) && is_array($config)) {
                /** @var array<string, mixed> $config */
                $validCustomServices[$name] = $config;
            }
        }

        return new CustomServiceCollection($validCustomServices);
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
        // Merge PHP config (keep base xdebug if not overridden)
        $phpData = $overrides['php'] ?? [];
        if (!is_array($phpData)) {
            $phpData = [];
        }

        $xdebugData = $phpData['xdebug'] ?? [];
        if (!is_array($xdebugData)) {
            $xdebugData = [];
        }

        $xdebugEnabled = $xdebugData['enabled'] ?? $base->php->xdebug->enabled;
        if (!is_bool($xdebugEnabled)) {
            $xdebugEnabled = $base->php->xdebug->enabled;
        }

        $xdebugIdeKey = $xdebugData['ide_key'] ?? $base->php->xdebug->ideKey;
        if (!is_string($xdebugIdeKey)) {
            $xdebugIdeKey = $base->php->xdebug->ideKey;
        }

        $xdebugClientHost = $xdebugData['client_host'] ?? $base->php->xdebug->clientHost;
        if (!is_string($xdebugClientHost)) {
            $xdebugClientHost = $base->php->xdebug->clientHost;
        }

        $xdebug = new XdebugConfig(
            enabled: $xdebugEnabled,
            ideKey: $xdebugIdeKey,
            clientHost: $xdebugClientHost,
        );

        $versionString = $phpData['version'] ?? null;
        $phpVersion = is_string($versionString) ? PhpVersion::tryFrom($versionString) : null;
        $phpVersion = $phpVersion ?? $base->php->version;

        $php = new PhpConfig(
            version: $phpVersion,
            xdebug: $xdebug,
        );

        // Merge services
        $mergedServices = $base->services->all();
        $servicesData = $overrides['services'] ?? [];
        if (is_array($servicesData)) {
            foreach ($servicesData as $name => $serviceData) {
                if (!is_string($name) || !is_array($serviceData)) {
                    continue;
                }

                $enabled = $serviceData['enabled'] ?? true;
                if (!is_bool($enabled)) {
                    $enabled = true;
                }

                $type = $serviceData['type'] ?? $name;
                if (!is_string($type)) {
                    $type = $name;
                }

                $version = $serviceData['version'] ?? 'latest';
                if (!is_string($version)) {
                    $version = 'latest';
                }

                $port = $serviceData['port'] ?? 0;
                if (!is_int($port)) {
                    $port = 0;
                }

                $additionalPorts = $serviceData['additional_ports'] ?? [];
                if (!is_array($additionalPorts)) {
                    $additionalPorts = [];
                }
                /** @var list<int> $portsList */
                $portsList = [];
                foreach ($additionalPorts as $p) {
                    if (is_int($p)) {
                        $portsList[] = $p;
                    }
                }

                $environmentVariables = $serviceData['environment'] ?? [];
                if (!is_array($environmentVariables)) {
                    $environmentVariables = [];
                }
                /** @var array<string, string> $envVars */
                $envVars = array_filter($environmentVariables, function ($value, $key) {
                    return is_string($key) && is_string($value);
                }, ARRAY_FILTER_USE_BOTH);

                $mergedServices[$name] = new ServiceConfig(
                    name: $name,
                    enabled: $enabled,
                    type: Service::from($type),
                    version: $version,
                    port: $port,
                    additionalPorts: $portsList,
                    environmentVariables: $envVars,
                );
            }
        }

        $services = new ServiceCollection($mergedServices);

        // Merge volumes
        $volumesData = $overrides['volumes'] ?? [];
        if (!is_array($volumesData)) {
            $volumesData = [];
        }

        $persistData = $volumesData['persist'] ?? $base->volumes->persist;
        if (!is_array($persistData)) {
            $persistData = $base->volumes->persist;
        }

        /** @var list<string> $persistList */
        $persistList = [];
        foreach ($persistData as $volume) {
            if (is_string($volume)) {
                $persistList[] = $volume;
            }
        }

        $volumes = new VolumeConfig($persistList);

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
            php: $php,
            services: $services,
            volumes: $volumes,
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
