<?php

declare(strict_types=1);

// ABOUTME: Registry of all available services.
// ABOUTME: Manages service registration and retrieval.

namespace Seaman\Service\Container;

use Seaman\Enum\Service;
use Seaman\ValueObject\Configuration;

class ServiceRegistry
{
    /** @var array<string, ServiceInterface> */
    private array $services = [];

    public static function create(): ServiceRegistry
    {
        {
            $registry = new self();

            foreach (get_declared_classes() as $className) {
                if (!str_starts_with($className, 'Seaman\\Service\\Container\\')) {
                    continue;
                }

                try {
                    $reflection = new \ReflectionClass($className);

                    if (!$reflection->implementsInterface(ServiceInterface::class)) {
                        continue;
                    }

                    if (!$reflection->isInstantiable()) {
                        continue;
                    }

                    /** @var ServiceInterface $service */
                    $service = $reflection->newInstance();
                    $registry->register($service);

                } catch (\Throwable) {
                    continue;
                }
            }

            return $registry;
        }



    }

    public function register(ServiceInterface $service): void
    {
        $this->services[$service->getName()] = $service;
    }

    public function get(Service $name): ServiceInterface
    {
        if (!isset($this->services[$name->value])) {
            throw new \InvalidArgumentException("Service '{$name->value}' not found");
        }

        return $this->services[$name->value];
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
