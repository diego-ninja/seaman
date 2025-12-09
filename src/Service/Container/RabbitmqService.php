<?php

declare(strict_types=1);

// ABOUTME: RabbitMQ message queue service implementation.
// ABOUTME: Configures RabbitMQ container with management UI.

namespace Seaman\Service\Container;

use Seaman\Enum\Service;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

readonly class RabbitmqService extends AbstractService
{
    public function getType(): Service
    {
        return Service::RabbitMq;
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: $this->getType()->value,
            enabled: false,
            type: $this->getType(),
            version: '3-management',
            port: $this->getType()->port(),
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
        $composeConfig = [
            'image' => 'rabbitmq:' . $config->version,
            'environment' => $config->environmentVariables,
            'ports' => [
                $config->port . ':5672',
                '15672:15672',
            ],
            'volumes' => ['rabbitmq_data:/var/lib/rabbitmq'],
        ];

        return $this->addHealthCheckToConfig($composeConfig);
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

    /**
     * @return array<string, string|int>
     */
    public function getEnvVariables(ServiceConfig $config): array
    {
        return [
            'RABBITMQ_PORT' => $config->port,
            'RABBITMQ_MANAGEMENT_PORT' => $config->additionalPorts[0] ?? 15672,
            'RABBITMQ_USER' => $config->environmentVariables['RABBITMQ_DEFAULT_USER'] ?? 'seaman',
            'RABBITMQ_PASSWORD' => $config->environmentVariables['RABBITMQ_DEFAULT_PASS'] ?? 'seaman',
        ];
    }

    public function getInspectInfo(ServiceConfig $config): string
    {
        $env = $config->environmentVariables;

        return sprintf(
            'v%s | %s:%s',
            $config->version,
            $env['RABBITMQ_DEFAULT_USER'] ?? 'seaman',
            $env['RABBITMQ_DEFAULT_PASS'] ?? 'seaman',
        );
    }
}
