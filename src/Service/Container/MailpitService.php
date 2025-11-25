<?php

declare(strict_types=1);

// ABOUTME: Mailpit email testing service implementation.
// ABOUTME: Configures Mailpit for local email capture.

namespace Seaman\Service\Container;

use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

readonly class MailpitService implements ServiceInterface
{
    public function getName(): string
    {
        return 'mailpit';
    }

    public function getDisplayName(): string
    {
        return 'Mailpit';
    }

    public function getDescription(): string
    {
        return 'Email testing tool - captures and displays emails';
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
            name: 'mailpit',
            enabled: false,
            type: 'mailpit',
            version: 'latest',
            port: 8025,
            additionalPorts: [1025],
            environmentVariables: [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function generateComposeConfig(ServiceConfig $config): array
    {
        $healthCheck = $this->getHealthCheck();
        $composeConfig = [
            'image' => 'axllent/mailpit:latest',
            'ports' => [
                '${MAILPIT_PORT}:8025',
                '${MAILPIT_SMTP_PORT:-1025}:1025',
            ],
            'networks' => ['seaman'],
            'environment' => [
                'MP_MAX_MESSAGES=5000',
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
        return [8025, 1025];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return new HealthCheck(
            test: ['CMD', 'wget', '--quiet', '--tries=1', '--spider', 'http://localhost:8025/'],
            interval: '10s',
            timeout: '5s',
            retries: 3,
        );
    }
}
