<?php

declare(strict_types=1);

// ABOUTME: Elasticsearch search engine service implementation.
// ABOUTME: Configures Elasticsearch container for full-text search.

namespace Seaman\Service\Container;

use Seaman\Enum\Service;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

readonly class ElasticsearchService extends AbstractService
{
    public function getType(): Service
    {
        return Service::Elasticsearch;
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: $this->getType()->value,
            enabled: false,
            type: $this->getType(),
            version: '9.2.1',
            port: $this->getType()->port(),
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
        $composeConfig = [
            'image' => 'docker.elastic.co/elasticsearch/elasticsearch:' . $config->version,
            'environment' => $config->environmentVariables,
            'ports' => [$config->port . ':9200'],
            'volumes' => ['elasticsearch_data:/usr/share/elasticsearch/data'],
        ];

        return $this->addHealthCheckToConfig($composeConfig);
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

    /**
     * @return array<string, string|int>
     */
    public function getEnvVariables(ServiceConfig $config): array
    {
        return [
            'ELASTICSEARCH_PORT' => $config->port,
        ];
    }
}
