<?php

declare(strict_types=1);

// ABOUTME: Parses custom services configuration section from YAML data.
// ABOUTME: Handles user-defined Docker service configurations.

namespace Seaman\Service\ConfigParser;

use Seaman\ValueObject\CustomServiceCollection;

final readonly class CustomServiceParser
{
    use ConfigDataExtractor;

    /**
     * @param array<string, mixed> $data
     */
    public function parse(array $data): CustomServiceCollection
    {
        $customData = $this->getArray($data, 'custom_services');

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
}
