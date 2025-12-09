<?php

declare(strict_types=1);

// ABOUTME: Mailpit email testing service implementation.
// ABOUTME: Configures Mailpit for local email capture.

namespace Seaman\Service\Container;

use Seaman\Enum\Service;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

readonly class MailpitService extends AbstractService
{
    public function getType(): Service
    {
        return Service::Mailpit;
    }

    public function getDefaultConfig(): ServiceConfig
    {
        return new ServiceConfig(
            name: $this->getType()->value,
            enabled: false,
            type: $this->getType(),
            version: 'latest',
            port: $this->getType()->port(),
            additionalPorts: [1025],
            environmentVariables: [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function generateComposeConfig(ServiceConfig $config): array
    {
        $composeConfig = [
            'image' => 'axllent/mailpit:' . $config->version,
            'ports' => [
                '${MAILPIT_PORT}:8025',
                '${MAILPIT_SMTP_PORT:-1025}:1025',
            ],
            'networks' => ['seaman'],
            'environment' => ['MP_MAX_MESSAGES=5000'],
        ];

        return $this->addHealthCheckToConfig($composeConfig);
    }

    /**
     * @return list<int>
     */
    public function getRequiredPorts(): array
    {
        return [8025, 1025];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return new HealthCheck(
            test: ['CMD', 'wget', '--spider', '-q', 'http://localhost:8025/livez'],
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
            'MAILPIT_PORT' => $config->port,
            'MAILPIT_SMTP_PORT' => $config->additionalPorts[0] ?? 1025,
        ];
    }
}
