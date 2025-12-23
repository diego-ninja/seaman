<?php

// ABOUTME: Test fixture for plugin loading tests.
// ABOUTME: Minimal valid plugin implementation.

declare(strict_types=1);

namespace Seaman\Tests\Fixtures\Plugins\ValidPlugin;

use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\PluginInterface;

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
}
