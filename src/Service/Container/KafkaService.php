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
            version: 'latest',
            port: $this->getType()->port(),
            additionalPorts: [],
            environmentVariables: [
                'KAFKA_NODE_ID' => '1',
                'KAFKA_PROCESS_ROLES' => 'broker,controller',
                'KAFKA_LISTENERS' => 'PLAINTEXT://:9092,CONTROLLER://:9093',
                'KAFKA_ADVERTISED_LISTENERS' => 'PLAINTEXT://kafka:9092',
                'KAFKA_LISTENER_SECURITY_PROTOCOL_MAP' => 'CONTROLLER:PLAINTEXT,PLAINTEXT:PLAINTEXT',
                'KAFKA_CONTROLLER_QUORUM_VOTERS' => '1@kafka:9093',
                'KAFKA_CONTROLLER_LISTENER_NAMES' => 'CONTROLLER',
                'KAFKA_OFFSETS_TOPIC_REPLICATION_FACTOR' => '1',
                'KAFKA_TRANSACTION_STATE_LOG_REPLICATION_FACTOR' => '1',
                'KAFKA_TRANSACTION_STATE_LOG_MIN_ISR' => '1',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function generateComposeConfig(ServiceConfig $config): array
    {
        $composeConfig = [
            'image' => 'apache/kafka:' . $config->version,
            'environment' => $config->environmentVariables,
            'ports' => [$config->port . ':9092'],
            'volumes' => ['kafka_data:/var/lib/kafka/data'],
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
            test: ['CMD-SHELL', '/opt/kafka/bin/kafka-cluster.sh cluster-id --bootstrap-server localhost:9092 || exit 1'],
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
