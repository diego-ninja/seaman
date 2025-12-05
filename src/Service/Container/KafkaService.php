<?php

declare(strict_types=1);

// ABOUTME: Apache Kafka distributed event streaming service implementation.
// ABOUTME: Configures Kafka container for message queuing and event streaming.

namespace Seaman\Service\Container;

use Seaman\Enum\Service;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

readonly class KafkaService extends AbstractService
{
    public function getType(): Service
    {
        return Service::Kafka;
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: $this->getType()->value,
            enabled: false,
            type: $this->getType(),
            version: '3.7',
            port: $this->getType()->port(),
            additionalPorts: [],
            environmentVariables: [
                'KAFKA_CFG_NODE_ID' => '0',
                'KAFKA_CFG_PROCESS_ROLES' => 'controller,broker',
                'KAFKA_CFG_LISTENERS' => 'PLAINTEXT://:9092,CONTROLLER://:9093',
                'KAFKA_CFG_LISTENER_SECURITY_PROTOCOL_MAP' => 'CONTROLLER:PLAINTEXT,PLAINTEXT:PLAINTEXT',
                'KAFKA_CFG_CONTROLLER_QUORUM_VOTERS' => '0@kafka:9093',
                'KAFKA_CFG_CONTROLLER_LISTENER_NAMES' => 'CONTROLLER',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function generateComposeConfig(ServiceConfig $config): array
    {
        $composeConfig = [
            'image' => 'bitnami/kafka:' . $config->version,
            'environment' => $config->environmentVariables,
            'ports' => [$config->port . ':9092'],
            'volumes' => ['kafka_data:/bitnami/kafka'],
        ];

        return $this->addHealthCheckToConfig($composeConfig);
    }

    /**
     * @return list<int>
     */
    public function getRequiredPorts(): array
    {
        return [9092];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return new HealthCheck(
            test: ['CMD-SHELL', 'kafka-broker-api-versions.sh --bootstrap-server localhost:9092 || exit 1'],
            interval: '10s',
            timeout: '10s',
            retries: 5,
        );
    }

    /**
     * @return array<string, string|int>
     */
    public function getEnvVariables(ServiceConfig $config): array
    {
        return [
            'KAFKA_PORT' => $config->port,
            'KAFKA_BROKER' => 'localhost:' . $config->port,
        ];
    }
}
