<?php

declare(strict_types=1);

// ABOUTME: Imports docker-compose.yaml files into seaman configuration.
// ABOUTME: Detects recognized services and preserves custom services.

namespace Seaman\Service;

use RuntimeException;
use Seaman\Service\Detector\ServiceDetector;
use Seaman\ValueObject\CustomServiceCollection;
use Seaman\ValueObject\ImportResult;
use Seaman\ValueObject\RecognizedService;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final readonly class ComposeImporter
{
    public function __construct(
        private ServiceDetector $detector,
    ) {}

    /**
     * Import a docker-compose file and categorize services.
     *
     * @throws RuntimeException When file not found, invalid YAML, or no services
     */
    public function import(string $composePath): ImportResult
    {
        if (!file_exists($composePath)) {
            throw new RuntimeException("docker-compose file not found: {$composePath}");
        }

        $content = file_get_contents($composePath);
        if ($content === false) {
            throw new RuntimeException("Failed to read docker-compose file: {$composePath}");
        }

        try {
            $composeData = Yaml::parse($content);
        } catch (ParseException $e) {
            throw new RuntimeException('Failed to parse docker-compose YAML: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($composeData)) {
            throw new RuntimeException('Invalid docker-compose structure');
        }

        if (!isset($composeData['services']) || !is_array($composeData['services'])) {
            throw new RuntimeException('No services found in docker-compose file');
        }

        /** @var array<string, array<string, mixed>> $services */
        $services = $composeData['services'];

        return $this->categorizeServices($services);
    }

    /**
     * @param array<string, array<string, mixed>> $services
     */
    private function categorizeServices(array $services): ImportResult
    {
        /** @var array<string, RecognizedService> $recognized */
        $recognized = [];

        /** @var array<string, array<string, mixed>> $custom */
        $custom = [];

        foreach ($services as $name => $config) {
            $detected = $this->detector->detectService($name, $config);

            if ($detected !== null) {
                $recognized[$name] = new RecognizedService(
                    detected: $detected,
                    config: $config,
                );
            } else {
                $custom[$name] = $config;
            }
        }

        return new ImportResult(
            recognized: $recognized,
            custom: new CustomServiceCollection($custom),
        );
    }
}
