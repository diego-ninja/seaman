<?php

// ABOUTME: Extracts service definitions from plugin methods.
// ABOUTME: Scans for methods with ProvidesService attribute.

declare(strict_types=1);

namespace Seaman\Plugin\Extractor;

use ReflectionClass;
use ReflectionMethod;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;

final readonly class ServiceExtractor
{
    /**
     * @return list<ServiceDefinition>
     */
    public function extract(PluginInterface $plugin): array
    {
        $services = [];
        $reflection = new ReflectionClass($plugin);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(ProvidesService::class);

            if (empty($attributes)) {
                continue;
            }

            /** @var ServiceDefinition $service */
            $service = $method->invoke($plugin);
            $services[] = $service;
        }

        return $services;
    }
}
