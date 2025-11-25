<?php

declare(strict_types=1);

// ABOUTME: Registry of all available services.
// ABOUTME: Manages service registration and retrieval.

namespace Seaman\Service\Container;

use Seaman\ValueObject\Configuration;

class ServiceRegistry
{
    /** @var array<string, ServiceInterface> */
    private array $services = [];

    public function register(ServiceInterface $service): void
    {
        $this->services[$service->getName()] = $service;
    }

    public function get(string $name): ServiceInterface
    {
        if (!isset($this->services[$name])) {
            throw new \InvalidArgumentException("Service '{$name}' not found");
        }

        return $this->services[$name];
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
    public function available(Configuration $config): array
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
