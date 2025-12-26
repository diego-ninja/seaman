<?php

// ABOUTME: Extracts lifecycle event handlers from plugin methods.
// ABOUTME: Scans for methods with OnLifecycle attribute.

declare(strict_types=1);

namespace Seaman\Plugin\Extractor;

use ReflectionClass;
use ReflectionMethod;
use Seaman\Plugin\Attribute\OnLifecycle;
use Seaman\Plugin\LifecycleHandler;
use Seaman\Plugin\PluginInterface;

final readonly class LifecycleExtractor
{
    /**
     * @return list<LifecycleHandler>
     */
    public function extract(PluginInterface $plugin): array
    {
        $handlers = [];
        $reflection = new ReflectionClass($plugin);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(OnLifecycle::class);

            if (empty($attributes)) {
                continue;
            }

            /** @var OnLifecycle $attr */
            $attr = $attributes[0]->newInstance();

            $handlers[] = new LifecycleHandler(
                event: $attr->event,
                priority: $attr->priority,
                handler: $method->getClosure($plugin),
            );
        }

        return $handlers;
    }
}
