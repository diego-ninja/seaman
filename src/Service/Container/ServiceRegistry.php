<?php

declare(strict_types=1);

// ABOUTME: Registry of all available services.
// ABOUTME: Manages service registration and retrieval.

namespace Seaman\Service\Container;

use Seaman\Enum\Service;
use Seaman\Plugin\Extractor\ServiceExtractor;
use Seaman\Plugin\PluginRegistry;
use Seaman\Plugin\PluginServiceAdapter;
use Seaman\ValueObject\Configuration;

class ServiceRegistry
{
    /** @var array<string, ServiceInterface> */
    private array $services = [];

    /**
     * Create a ServiceRegistry with core services and optionally bundled plugins.
     *
     * @param bool $includeBundledPlugins Whether to automatically load bundled plugins
     */
    public static function create(bool $includeBundledPlugins = true): ServiceRegistry
    {
        $registry = new self();

        // Load core services (currently only Traefik)
        $discovery = new ServiceDiscovery(__DIR__);
        foreach ($discovery->discover() as $service) {
            $registry->register($service);
        }

        // Load bundled plugin services
        if ($includeBundledPlugins) {
            $bundledPluginsDir = self::getBundledPluginsDir();
            if ($bundledPluginsDir !== null && is_dir($bundledPluginsDir)) {
                $pluginRegistry = PluginRegistry::discover(
                    projectRoot: getcwd() ?: '.',
                    localPluginsDir: '',
                    pluginConfig: [],
                    bundledPluginsDir: $bundledPluginsDir,
                );
                $registry->registerPluginServices($pluginRegistry);
            }
        }

        return $registry;
    }

    /**
     * Get the bundled plugins directory path.
     */
    private static function getBundledPluginsDir(): ?string
    {
        // Use Phar::running(true) to get path WITH phar:// prefix
        // This is required to access files inside the PHAR archive
        $pharPath = \Phar::running(true);

        if ($pharPath !== '') {
            return $pharPath . '/plugins';
        }

        // When running from source, plugins are at repo root
        $sourceDir = dirname(__DIR__, 3) . '/plugins';
        if (is_dir($sourceDir)) {
            return $sourceDir;
        }

        return null;
    }

    public function register(ServiceInterface $service): void
    {
        $this->services[$service->getName()] = $service;
    }

    public function registerPluginServices(PluginRegistry $pluginRegistry): void
    {
        $extractor = new ServiceExtractor();

        foreach ($pluginRegistry->all() as $loadedPlugin) {
            $serviceDefinitions = $extractor->extract($loadedPlugin->instance);

            foreach ($serviceDefinitions as $definition) {
                $adapter = new PluginServiceAdapter($definition);
                $this->register($adapter);
            }
        }
    }

    public function get(Service $name): ServiceInterface
    {
        if (!isset($this->services[$name->value])) {
            throw new \InvalidArgumentException("Service '{$name->value}' not found");
        }

        return $this->services[$name->value];
    }

    public function has(string $name): bool
    {
        return isset($this->services[$name]);
    }

    public function getByName(string $name): ?ServiceInterface
    {
        return $this->services[$name] ?? null;
    }

    /**
     * @return array<string, ServiceInterface>
     */
    public function all(): array
    {
        return $this->services;
    }

    /**
     * @return list<ServiceInterface> Services not currently enabled
     */
    public function disabled(Configuration $config): array
    {
        $enabledNames = array_keys($config->services->enabled());
        $available = [];

        foreach ($this->services as $name => $service) {
            if (!in_array($name, $enabledNames, true)) {
                $available[] = $service;
            }
        }

        return $available;
    }

    /**
     * @return list<ServiceInterface> Currently enabled services
     */
    public function enabled(Configuration $config): array
    {
        $enabledNames = array_keys($config->services->enabled());
        $enabled = [];

        foreach ($this->services as $name => $service) {
            if (in_array($name, $enabledNames, true)) {
                $enabled[] = $service;
            }
        }

        return $enabled;
    }
}
