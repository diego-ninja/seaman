<?php

declare(strict_types=1);

// ABOUTME: Tests for ServiceInterface implementations.
// ABOUTME: Validates service contract compliance.

namespace Seaman\Tests\Unit\Service\Container;

use Seaman\Service\Container\ServiceInterface;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\HealthCheck;

test('mock service implements interface correctly', function () {
    $service = new class implements ServiceInterface {
        public function getName(): string
        {
            return 'test';
        }
        public function getDisplayName(): string
        {
            return 'Test Service';
        }
        public function getDescription(): string
        {
            return 'A test service';
        }
        public function getDependencies(): array
        {
            return [];
        }
        public function getDefaultConfig(): ServiceConfig
        {
            return new ServiceConfig('test', true, 'test', 'latest', 9999, [], []);
        }
        public function generateComposeConfig(ServiceConfig $config): array
        {
            return ['image' => 'test:latest'];
        }
        public function getRequiredPorts(): array
        {
            return [9999];
        }
        public function getHealthCheck(): ?HealthCheck
        {
            return new HealthCheck(['CMD', 'true'], '10s', '5s', 3);
        }

        public function getEnvVariables(ServiceConfig $config): array
        {
            return [];
        }
    };

    expect($service->getName())->toBe('test')
        ->and($service->getDisplayName())->toBe('Test Service')
        ->and($service->getDescription())->toBe('A test service')
        ->and($service->getDependencies())->toBe([])
        ->and($service->getRequiredPorts())->toBe([9999])
        ->and($service->getHealthCheck())->toBeInstanceOf(HealthCheck::class);
});
