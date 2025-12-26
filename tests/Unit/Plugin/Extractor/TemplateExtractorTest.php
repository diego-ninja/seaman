<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Extractor;

use Seaman\Plugin\Extractor\TemplateExtractor;
use Seaman\Plugin\TemplateOverride;
use Seaman\Tests\Fixtures\Plugins\ValidPlugin\ValidPlugin;

test('TemplateExtractor finds OverridesTemplate methods', function (): void {
    $extractor = new TemplateExtractor();
    $plugin = new ValidPlugin();

    $overrides = $extractor->extract($plugin);

    expect($overrides)->toHaveCount(1);
    expect($overrides[0])->toBeInstanceOf(TemplateOverride::class);
    expect($overrides[0]->originalTemplate)->toBe('docker/app.dockerfile.twig');
});

test('TemplateExtractor returns empty for plugins without overrides', function (): void {
    $extractor = new TemplateExtractor();

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

    $overrides = $extractor->extract($plugin);

    expect($overrides)->toBe([]);
});
