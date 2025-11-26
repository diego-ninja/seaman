<?php

declare(strict_types=1);

// ABOUTME: Manages seaman.yaml configuration loading and saving.
// ABOUTME: Handles YAML parsing and Configuration object creation.

namespace Seaman\Service;

use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\VolumeConfig;
use Seaman\ValueObject\XdebugConfig;
use Symfony\Component\Yaml\Yaml;

readonly class ConfigManager
{
    public function __construct(
        private string $projectRoot,
    ) {}

    public function load(): Configuration
    {
        $yamlPath = $this->projectRoot . '/seaman.yaml';

        if (!file_exists($yamlPath)) {
            throw new \RuntimeException('seaman.yaml not found at ' . $yamlPath);
        }

        $content = file_get_contents($yamlPath);
        if ($content === false) {
            throw new \RuntimeException('Failed to read seaman.yaml');
        }

        try {
            $data = Yaml::parse($content);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to parse YAML: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($data)) {
            throw new \RuntimeException('Invalid YAML structure');
        }

        /** @var array<string, mixed> $data */
        return $this->parseConfiguration($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function parseConfiguration(array $data): Configuration
    {
        // Check for old config format with server field
        if (isset($data['server'])) {
            throw new \RuntimeException(
                'Old configuration format detected. The "server" field is no longer supported. ' .
                'Please run "seaman init" to reinitialize your configuration.'
            );
        }

        $phpData = $data['php'] ?? [];
        if (!is_array($phpData)) {
            throw new \RuntimeException('Invalid PHP configuration');
        }

        $xdebugData = $phpData['xdebug'] ?? [];
        if (!is_array($xdebugData)) {
            throw new \RuntimeException('Invalid xdebug configuration');
        }

        $xdebugEnabled = $xdebugData['enabled'] ?? false;
        if (!is_bool($xdebugEnabled)) {
            throw new \RuntimeException('Xdebug enabled must be a boolean');
        }

        $xdebugIdeKey = $xdebugData['ide_key'] ?? 'PHPSTORM';
        if (!is_string($xdebugIdeKey)) {
            throw new \RuntimeException('Xdebug IDE key must be a string');
        }

        $xdebugClientHost = $xdebugData['client_host'] ?? 'host.docker.internal';
        if (!is_string($xdebugClientHost)) {
            throw new \RuntimeException('Xdebug client host must be a string');
        }

        $xdebug = new XdebugConfig(
            enabled: $xdebugEnabled,
            ideKey: $xdebugIdeKey,
            clientHost: $xdebugClientHost,
        );

        $extensions = $phpData['extensions'] ?? [];
        if (!is_array($extensions)) {
            throw new \RuntimeException('Invalid extensions configuration');
        }

        $phpVersion = $phpData['version'] ?? '8.4';
        if (!is_string($phpVersion)) {
            throw new \RuntimeException('PHP version must be a string');
        }

        $extensionsList = [];
        foreach ($extensions as $extension) {
            if (is_string($extension)) {
                $extensionsList[] = $extension;
            }
        }

        $php = new PhpConfig(
            version: $phpVersion,
            extensions: $extensionsList,
            xdebug: $xdebug,
        );

        $servicesData = $data['services'] ?? [];
        if (!is_array($servicesData)) {
            throw new \RuntimeException('Invalid services configuration');
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
            $envVars = [];
            foreach ($environmentVariables as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $envVars[$key] = $value;
                }
            }

            $services[$name] = new ServiceConfig(
                name: $name,
                enabled: $enabled,
                type: $type,
                version: $version,
                port: $port,
                additionalPorts: $portsList,
                environmentVariables: $envVars,
            );
        }

        $volumesData = $data['volumes'] ?? [];
        if (!is_array($volumesData)) {
            throw new \RuntimeException('Invalid volumes configuration');
        }

        $persistData = $volumesData['persist'] ?? [];
        if (!is_array($persistData)) {
            throw new \RuntimeException('Invalid persist configuration');
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

        return new Configuration(
            version: $version,
            php: $php,
            services: new ServiceCollection($services),
            volumes: $volumes,
        );
    }

    public function save(Configuration $config): void
    {
        $data = [
            'version' => $config->version,
            'php' => [
                'version' => $config->php->version,
                'extensions' => $config->php->extensions,
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
                'type' => $service->type,
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

        $yamlContent = Yaml::dump($data, 4, 2);
        $yamlPath = $this->projectRoot . '/seaman.yaml';

        if (file_put_contents($yamlPath, $yamlContent) === false) {
            throw new \RuntimeException('Failed to write seaman.yaml');
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

        $phpVersion = $phpData['version'] ?? $base->php->version;
        if (!is_string($phpVersion)) {
            $phpVersion = $base->php->version;
        }

        $phpExtensions = $phpData['extensions'] ?? $base->php->extensions;
        if (!is_array($phpExtensions)) {
            $phpExtensions = $base->php->extensions;
        }

        $extensionsList = [];
        foreach ($phpExtensions as $extension) {
            if (is_string($extension)) {
                $extensionsList[] = $extension;
            }
        }

        $php = new PhpConfig(
            version: $phpVersion,
            extensions: $extensionsList,
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
                $envVars = [];
                foreach ($environmentVariables as $key => $value) {
                    if (is_string($key) && is_string($value)) {
                        $envVars[$key] = $value;
                    }
                }

                $mergedServices[$name] = new ServiceConfig(
                    name: $name,
                    enabled: $enabled,
                    type: $type,
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

        return new Configuration(
            version: $version,
            php: $php,
            services: $services,
            volumes: $volumes,
        );
    }

    private function generateEnv(Configuration $config): void
    {
        $lines = [
            '# Application configuration',
            'APP_PORT=8000',
            '',
            '# PHP configuration',
            'PHP_VERSION=' . $config->php->version,
            '',
            '# Xdebug configuration',
            'XDEBUG_MODE=' . ($config->php->xdebug->enabled ? 'debug' : 'off'),
            '',
        ];

        foreach ($config->services->all() as $name => $service) {
            $lines[] = '# ' . ucfirst($name) . ' configuration';
            $lines[] = strtoupper($name) . '_PORT=' . $service->port;

            if (!empty($service->environmentVariables)) {
                foreach ($service->environmentVariables as $key => $value) {
                    $lines[] = strtoupper($name) . '_' . $key . '=' . $value;
                }
            }

            $lines[] = '';
        }

        $envContent = implode("\n", $lines);
        $envPath = $this->projectRoot . '/.env';

        if (file_put_contents($envPath, $envContent) === false) {
            throw new \RuntimeException('Failed to write .env');
        }
    }
}
