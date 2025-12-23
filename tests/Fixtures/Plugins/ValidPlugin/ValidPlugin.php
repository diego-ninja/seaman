<?php

// ABOUTME: Test fixture for plugin loading tests.
// ABOUTME: Demonstrates all plugin extension points.

declare(strict_types=1);

namespace Seaman\Tests\Fixtures\Plugins\ValidPlugin;

use Seaman\Enum\ServiceCategory;
use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\Attribute\OnLifecycle;
use Seaman\Plugin\Attribute\OverridesTemplate;
use Seaman\Plugin\Attribute\ProvidesCommand;
use Seaman\Plugin\Attribute\ProvidesService;
use Seaman\Plugin\PluginInterface;
use Seaman\Plugin\ServiceDefinition;
use Symfony\Component\Console\Command\Command;

#[AsSeamanPlugin(name: 'valid-plugin', version: '1.0.0', description: 'A valid test plugin')]
final class ValidPlugin implements PluginInterface
{
    public function getName(): string
    {
        return 'valid-plugin';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'A valid test plugin';
    }

    #[ProvidesService(name: 'custom-redis', category: ServiceCategory::Cache)]
    public function customRedis(): ServiceDefinition
    {
        return new ServiceDefinition(
            name: 'custom-redis',
            template: __DIR__ . '/templates/redis.yaml.twig',
            defaultConfig: ['port' => 6379],
        );
    }

    #[ProvidesCommand]
    public function statusCommand(): Command
    {
        return new Command('valid-plugin:status');
    }

    #[OnLifecycle(event: 'before:start', priority: 10)]
    public function onBeforeStart(): void
    {
        // Hook logic here
    }

    #[OverridesTemplate(template: 'docker/app.dockerfile.twig')]
    public function customDockerfile(): string
    {
        return __DIR__ . '/templates/app.dockerfile.twig';
    }
}
