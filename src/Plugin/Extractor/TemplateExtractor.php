<?php

// ABOUTME: Extracts template overrides from plugin methods.
// ABOUTME: Scans for methods with OverridesTemplate attribute.

declare(strict_types=1);

namespace Seaman\Plugin\Extractor;

use ReflectionClass;
use ReflectionMethod;
use Seaman\Plugin\Attribute\OverridesTemplate;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\TemplateOverride;

final readonly class TemplateExtractor
{
    /**
     * @return list<TemplateOverride>
     */
    public function extract(PluginInterface $plugin): array
    {
        $overrides = [];
        $reflection = new ReflectionClass($plugin);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(OverridesTemplate::class);

            if (empty($attributes)) {
                continue;
            }

            /** @var OverridesTemplate $attr */
            $attr = $attributes[0]->newInstance();

            /** @var string $path */
            $path = $method->invoke($plugin);

            $overrides[] = new TemplateOverride(
                originalTemplate: $attr->template,
                overridePath: $path,
            );
        }

        return $overrides;
    }
}
