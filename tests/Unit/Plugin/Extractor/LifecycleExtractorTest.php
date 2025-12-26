<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Extractor;

use Seaman\Plugin\Extractor\LifecycleExtractor;
use Seaman\Plugin\LifecycleHandler;
use Seaman\Tests\Fixtures\Plugins\ValidPlugin\ValidPlugin;

test('LifecycleExtractor finds OnLifecycle methods', function (): void {
    $extractor = new LifecycleExtractor();
    $plugin = new ValidPlugin();

    $handlers = $extractor->extract($plugin);

    expect($handlers)->toHaveCount(1);
    expect($handlers[0])->toBeInstanceOf(LifecycleHandler::class);
    expect($handlers[0]->event)->toBe('before:start');
    expect($handlers[0]->priority)->toBe(10);
});

test('LifecycleExtractor returns empty for plugins without handlers', function (): void {
    $extractor = new LifecycleExtractor();

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

    $handlers = $extractor->extract($plugin);

    expect($handlers)->toBe([]);
});
