<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Plugin\Extractor;

use Seaman\Plugin\Extractor\CommandExtractor;
use Seaman\Tests\Fixtures\Plugins\ValidPlugin\ValidPlugin;
use Symfony\Component\Console\Command\Command;

test('CommandExtractor finds ProvidesCommand methods', function (): void {
    $extractor = new CommandExtractor();
    $plugin = new ValidPlugin();

    $commands = $extractor->extract($plugin);

    expect($commands)->toHaveCount(1);
    expect($commands[0])->toBeInstanceOf(Command::class);
    expect($commands[0]->getName())->toBe('valid-plugin:status');
});

test('CommandExtractor returns empty for plugins without commands', function (): void {
    $extractor = new CommandExtractor();

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

    $commands = $extractor->extract($plugin);

    expect($commands)->toBe([]);
});
