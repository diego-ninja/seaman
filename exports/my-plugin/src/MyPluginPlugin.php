<?php

declare(strict_types=1);

namespace TestVendor\MyPlugin;

use Seaman\Plugin\Attribute\AsSeamanPlugin;
use Seaman\Plugin\PluginInterface;

#[AsSeamanPlugin(
    name: 'my-plugin',
    version: '1.0.0',
)]
final class MyPluginPlugin implements PluginInterface
{
    public function getName(): string { return 'my-plugin'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getDescription(): string { return ''; }
}