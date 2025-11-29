<?php

declare(strict_types=1);

// ABOUTME: Elasticsearch search engine service implementation.
// ABOUTME: Configures Elasticsearch container for full-text search.

namespace Seaman\Service\Container;

use Seaman\Enum\Service;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

readonly class ElasticsearchService implements ServiceInterface
{
    public function getName(): string
    {
        return Service::Elasticsearch->value;
    }

    public function getDisplayName(): string
    {
        return Service::Elasticsearch->name;
    }

    public function getDescription(): string
    {
        return 'Elasticsearch search engine';
    }

    /**
     * @return list<string>
     */
    public function getDependencies(): array
    {
        return [];
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: Service::Elasticsearch->value,
            enabled: false,
            type: 'elasticsearch',
            version: '8.11',
            port: 9200,
            additionalPorts: [],
            environmentVariables: [
                'discovery.type' => 'single-node',
                'xpack.security.enabled' => 'false',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function generateComposeConfig(ServiceConfig $config): array
    {
        $healthCheck = $this->getHealthCheck();

        $composeConfig = [
            'image' => 'elasticsearch:' . $config->version,
            'environment' => $config->environmentVariables,
            'ports' => [
                $config->port . ':9200',
            ],
            'volumes' => [
                'elasticsearch_data:/usr/share/elasticsearch/data',
            ],
        ];

        if ($healthCheck !== null) {
            $composeConfig['healthcheck'] = [
                'test' => $healthCheck->test,
                'interval' => $healthCheck->interval,
                'timeout' => $healthCheck->timeout,
                'retries' => $healthCheck->retries,
            ];
        }

        return $composeConfig;
    }

    /**
     * @return list<int>
     */
    public function getRequiredPorts(): array
    {
        return [9200];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return new HealthCheck(
            test: ['CMD-SHELL', 'curl -f http://localhost:9200/_cluster/health || exit 1'],
            interval: '10s',
            timeout: '5s',
            retries: 5,
        );
    }
}
