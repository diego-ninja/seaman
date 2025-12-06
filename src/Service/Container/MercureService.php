<?php

declare(strict_types=1);

// ABOUTME: Mercure real-time updates hub service implementation.
// ABOUTME: Configures Mercure for Symfony real-time features with UX Turbo.

namespace Seaman\Service\Container;

use Seaman\Enum\Service;
use Seaman\ValueObject\HealthCheck;
use Seaman\ValueObject\ServiceConfig;

readonly class MercureService extends AbstractService
{
    public function getType(): Service
    {
        return Service::Mercure;
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
                'SERVER_NAME' => ':3000',
                'MERCURE_PUBLISHER_JWT_KEY' => '!ChangeThisMercureHubJWTSecretKey!',
                'MERCURE_SUBSCRIBER_JWT_KEY' => '!ChangeThisMercureHubJWTSecretKey!',
                'MERCURE_EXTRA_DIRECTIVES' => 'anonymous; cors_origins *',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function generateComposeConfig(ServiceConfig $config): array
    {
        $composeConfig = [
            'image' => 'dunglas/mercure:' . $config->version,
            'environment' => $config->environmentVariables,
            'ports' => [$config->port . ':3000'],
            'networks' => ['seaman'],
        ];

        return $this->addHealthCheckToConfig($composeConfig);
    }

    /**
     * @return list<int>
     */
    public function getRequiredPorts(): array
    {
        return [3000];
    }

    public function getHealthCheck(): ?HealthCheck
    {
        return new HealthCheck(
            test: ['CMD', 'wget', '--spider', '-q', 'http://localhost:3000/.well-known/mercure'],
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
            'MERCURE_PORT' => $config->port,
            'MERCURE_URL' => 'http://mercure:3000/.well-known/mercure',
            'MERCURE_PUBLIC_URL' => 'http://localhost:' . $config->port . '/.well-known/mercure',
            'MERCURE_JWT_SECRET' => $config->environmentVariables['MERCURE_PUBLISHER_JWT_KEY'] ?? '!ChangeThisMercureHubJWTSecretKey!',
        ];
    }
}
