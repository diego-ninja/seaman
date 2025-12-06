<?php

declare(strict_types=1);

// ABOUTME: OpenSearch search and analytics engine service implementation.
// ABOUTME: Configures OpenSearch as Elasticsearch alternative.

namespace Seaman\Service\Container;

use Seaman\Enum\Service;
use Seaman\ValueObject\HealthCheck;
use Seaman\ValueObject\ServiceConfig;

readonly class OpenSearchService extends AbstractService
{
    public function getType(): Service
    {
        return Service::OpenSearch;
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: $this->getType()->value,
            enabled: false,
            type: $this->getType(),
            version: '2',
            port: $this->getType()->port(),
            additionalPorts: [9600],
            environmentVariables: [
                'discovery.type' => 'single-node',
                'DISABLE_SECURITY_PLUGIN' => 'true',
                'OPENSEARCH_INITIAL_ADMIN_PASSWORD' => 'Admin123!',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function generateComposeConfig(ServiceConfig $config): array
    {
        $composeConfig = [
            'image' => 'opensearchproject/opensearch:' . $config->version,
            'environment' => $config->environmentVariables,
            'ports' => [
                $config->port . ':9200',
                '${OPENSEARCH_PERFORMANCE_PORT:-9600}:9600',
            ],
            'volumes' => ['opensearch_data:/usr/share/opensearch/data'],
            'networks' => ['seaman'],
        ];

        return $this->addHealthCheckToConfig($composeConfig);
    }

    /**
     * @return list<int>
     */
    public function getRequiredPorts(): array
    {
        return [9200, 9600];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return new HealthCheck(
            test: ['CMD-SHELL', 'curl -s http://localhost:9200/_cluster/health | grep -q -E "green|yellow"'],
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
            'OPENSEARCH_PORT' => $config->port,
            'OPENSEARCH_URL' => 'http://opensearch:9200',
        ];
    }
}
