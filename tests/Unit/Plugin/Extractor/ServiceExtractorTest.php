<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Extractor;

use Seaman\Plugin\Extractor\ServiceExtractor;
use Seaman\Plugin\ServiceDefinition;
use Seaman\Tests\Fixtures\Plugins\ValidPlugin\ValidPlugin;

test('ServiceExtractor finds ProvidesService methods', function (): void {
    $extractor = new ServiceExtractor();
    $plugin = new ValidPlugin();

    $services = $extractor->extract($plugin);

    expect($services)->toHaveCount(1);
    expect($services[0])->toBeInstanceOf(ServiceDefinition::class);
    expect($services[0]->name)->toBe('custom-redis');
});

test('ServiceExtractor returns empty for plugins without services', function (): void {
    $extractor = new ServiceExtractor();

    $plugin = new class implements \Seaman\Plugin\PluginInterface {
        public function getName(): string
        {
            return 'empty';
        }

        public function getVersion(): string
        {
            return '1.0.0';
        }

        public function getDescription(): string
        {
            return '';
        }
    };

    $services = $extractor->extract($plugin);

    expect($services)->toBe([]);
});
