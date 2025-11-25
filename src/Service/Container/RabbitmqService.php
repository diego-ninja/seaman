<?php

declare(strict_types=1);

// ABOUTME: RabbitMQ message queue service implementation.
// ABOUTME: Configures RabbitMQ container with management UI.

namespace Seaman\Service\Container;

use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

readonly class RabbitmqService implements ServiceInterface
{
    public function getName(): string
    {
        return 'rabbitmq';
    }

    public function getDisplayName(): string
    {
        return 'RabbitMQ';
    }

    public function getDescription(): string
    {
        return 'RabbitMQ message queue';
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
            name: 'rabbitmq',
            enabled: false,
            type: 'rabbitmq',
            version: '3-management',
            port: 5672,
            additionalPorts: [15672],
            environmentVariables: [
                'RABBITMQ_DEFAULT_USER' => 'seaman',
                'RABBITMQ_DEFAULT_PASS' => 'seaman',
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
            'image' => 'rabbitmq:' . $config->version,
            'environment' => $config->environmentVariables,
            'ports' => [
                $config->port . ':5672',
                '15672:15672',
            ],
            'volumes' => [
                'rabbitmq_data:/var/lib/rabbitmq',
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
        return [5672, 15672];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return new HealthCheck(
            test: ['CMD-SHELL', 'rabbitmq-diagnostics -q ping'],
            interval: '10s',
            timeout: '5s',
            retries: 5,
        );
    }
}
