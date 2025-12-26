<?php

declare(strict_types=1);

// ABOUTME: Manages seaman.yaml configuration loading and saving.
// ABOUTME: Handles YAML parsing and Configuration object creation.

namespace Seaman\Service;

use Seaman\Enum\ProjectType;
use Seaman\Exception\FileNotFoundException;
use Seaman\Exception\FileOperationException;
use Seaman\Exception\InvalidConfigurationException;
use Seaman\Service\ConfigParser\CustomServiceParser;
use Seaman\Service\ConfigParser\PhpConfigParser;
use Seaman\Service\ConfigParser\PluginConfigParser;
use Seaman\Service\ConfigParser\ProxyConfigParser;
use Seaman\Service\ConfigParser\ServiceConfigParser;
use Seaman\Service\ConfigParser\VolumeConfigParser;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\PortAllocation;
use Symfony\Component\Yaml\Yaml;

readonly class ConfigManager
{
    private PhpConfigParser $phpParser;
    private ServiceConfigParser $serviceParser;
    private VolumeConfigParser $volumeParser;
    private ProxyConfigParser $proxyParser;
    private CustomServiceParser $customServiceParser;
    private PluginConfigParser $pluginParser;
    private FileReader $fileReader;

    public function __construct(
        private string $projectRoot,
        private ServiceRegistry $serviceRegistry,
        private ConfigurationValidator $validator,
        ?FileReader $fileReader = null,
    ) {
        $this->phpParser = new PhpConfigParser();
        $this->serviceParser = new ServiceConfigParser();
        $this->volumeParser = new VolumeConfigParser();
        $this->proxyParser = new ProxyConfigParser();
        $this->customServiceParser = new CustomServiceParser();
        $this->pluginParser = new PluginConfigParser();
        $this->fileReader = $fileReader ?? new FileReader();
    }

    public function load(): Configuration
    {
        $yamlPath = $this->projectRoot . '/.seaman/seaman.yaml';

        if (!$this->fileReader->exists($yamlPath)) {
            throw FileNotFoundException::create($yamlPath, 'seaman.yaml not found');
        }

        $data = $this->fileReader->readYaml($yamlPath);

        if (empty($data)) {
            throw new InvalidConfigurationException('Invalid YAML structure: expected array');
        }

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
            throw new InvalidConfigurationException('project_name must be a string');
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
            plugins: $this->pluginParser->parse($data),
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
                'server' => $config->php->server->value,
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
        if ($proxy->dnsProvider !== null) {
            $data['proxy']['dns_provider'] = $proxy->dnsProvider->value;
        }

        // Add custom services if present
        if ($config->hasCustomServices()) {
            $data['custom_services'] = $config->customServices->all();
        }

        // Add plugins configuration if present
        if (!empty($config->plugins)) {
            $data['plugins'] = $config->plugins;
        }

        $yamlContent = Yaml::dump($data, 4, 2);

        // Ensure .seaman directory exists
        $seamanDir = $this->projectRoot . '/.seaman';
        if (!is_dir($seamanDir)) {
            mkdir($seamanDir, 0755, true);
        }

        $yamlPath = $seamanDir . '/seaman.yaml';

        if (file_put_contents($yamlPath, $yamlContent) === false) {
            throw FileOperationException::writeFailed($yamlPath);
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
        $this->generateEnvWithAllocation($config, new PortAllocation());
    }

    /**
     * Generate .env file with port allocations.
     *
     * Performs smart merging: preserves existing user variables while
     * updating Seaman-managed variables (those in the SEAMAN MANAGED section).
     *
     * When a port allocation differs from the desired port, the assigned
     * port will be used in the .env file instead.
     */
    public function generateEnvWithAllocation(Configuration $config, PortAllocation $allocation): void
    {
        $envPath = $this->projectRoot . '/.env';

        // Parse existing .env file to preserve user variables
        $existingVars = $this->parseExistingEnv($envPath);

        // Generate Seaman-managed variables
        $seamanVars = $this->generateSeamanEnvVars($config, $allocation);

        // Build final content: user variables first, then Seaman section
        $lines = [];

        // Add user variables (those not managed by Seaman)
        $userVars = array_diff_key($existingVars, $seamanVars);
        foreach ($userVars as $key => $value) {
            $lines[] = $key . '=' . $value;
        }

        if (!empty($userVars)) {
            $lines[] = '';
        }

        // Add Seaman managed section
        $lines[] = '# ---- SEAMAN MANAGED ----';
        $lines[] = '# Variables below are managed by Seaman. Manual changes may be overwritten.';
        $lines[] = '';
        $lines[] = '# Application configuration';
        $lines[] = 'APP_PORT=' . ($existingVars['APP_PORT'] ?? '8000');
        $lines[] = '';
        $lines[] = '# PHP configuration';
        $lines[] = 'PHP_VERSION=' . $config->php->version->value;
        $lines[] = '';
        $lines[] = '# Xdebug configuration';
        $lines[] = 'XDEBUG_MODE=' . ($config->php->xdebug->enabled ? 'debug' : 'off');
        $lines[] = '';

        foreach ($config->services->all() as $name => $serviceConfig) {
            if (!$serviceConfig->enabled) {
                continue;
            }

            try {
                $service = $this->serviceRegistry->get($serviceConfig->type);
                $envVars = $service->getEnvVariables($serviceConfig);

                // Apply port allocation overrides
                $envVars = $this->applyPortAllocation($envVars, $name, $serviceConfig, $allocation);

                $lines[] = '# ' . ucfirst($name) . ' configuration';
                foreach ($envVars as $key => $value) {
                    $lines[] = $key . '=' . $value;
                }
                $lines[] = '';
            } catch (\ValueError|\InvalidArgumentException $e) {
                continue;
            }
        }

        $lines[] = '# ---- END SEAMAN MANAGED ----';

        $envContent = implode("\n", $lines);

        if (file_put_contents($envPath, $envContent) === false) {
            throw FileOperationException::writeFailed($envPath);
        }
    }

    /**
     * Parse existing .env file and return variables as associative array.
     *
     * @return array<string, string>
     */
    private function parseExistingEnv(string $envPath): array
    {
        if (!file_exists($envPath)) {
            return [];
        }

        $content = file_get_contents($envPath);
        if ($content === false) {
            return [];
        }

        $vars = [];
        $inSeamanSection = false;

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            // Track Seaman managed section
            if (str_contains($line, '---- SEAMAN MANAGED ----')) {
                $inSeamanSection = true;
                continue;
            }
            if (str_contains($line, '---- END SEAMAN MANAGED ----')) {
                $inSeamanSection = false;
                continue;
            }

            // Skip comments and empty lines
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Parse KEY=value
            $equalsPos = strpos($line, '=');
            if ($equalsPos === false) {
                continue;
            }

            $key = substr($line, 0, $equalsPos);
            $value = substr($line, $equalsPos + 1);

            // Only keep variables outside Seaman section
            if (!$inSeamanSection) {
                $vars[$key] = $value;
            }
        }

        return $vars;
    }

    /**
     * Generate the set of Seaman-managed environment variable keys.
     *
     * @return array<string, bool>
     */
    private function generateSeamanEnvVars(Configuration $config, PortAllocation $allocation): array
    {
        $keys = [
            'APP_PORT' => true,
            'PHP_VERSION' => true,
            'XDEBUG_MODE' => true,
        ];

        foreach ($config->services->all() as $name => $serviceConfig) {
            if (!$serviceConfig->enabled) {
                continue;
            }

            try {
                $service = $this->serviceRegistry->get($serviceConfig->type);
                $envVars = $service->getEnvVariables($serviceConfig);

                foreach ($envVars as $key => $value) {
                    $keys[$key] = true;
                }
            } catch (\ValueError|\InvalidArgumentException $e) {
                continue;
            }
        }

        return $keys;
    }

    /**
     * Apply port allocation to environment variables.
     *
     * @param array<string, string|int> $envVars
     * @return array<string, string|int>
     */
    private function applyPortAllocation(
        array $envVars,
        string $serviceName,
        \Seaman\ValueObject\ServiceConfig $serviceConfig,
        PortAllocation $allocation,
    ): array {
        // Replace main port if allocated differently
        $mainPort = $serviceConfig->port;
        if ($mainPort > 0) {
            $assignedPort = $allocation->getPort($serviceName, $mainPort);
            foreach ($envVars as $key => $value) {
                if ($value === $mainPort) {
                    $envVars[$key] = $assignedPort;
                }
            }
        }

        // Replace additional ports if allocated differently
        foreach ($serviceConfig->additionalPorts as $desiredPort) {
            if ($desiredPort > 0) {
                $assignedPort = $allocation->getPort($serviceName, $desiredPort);
                foreach ($envVars as $key => $value) {
                    if ($value === $desiredPort) {
                        $envVars[$key] = $assignedPort;
                    }
                }
            }
        }

        return $envVars;
    }
}
