<?php

// ABOUTME: Contract that all plugins must implement.
// ABOUTME: Defines plugin identity and metadata methods.

declare(strict_types=1);

namespace Seaman\Plugin;

interface PluginInterface
{
    public function getName(): string;

    public function getVersion(): string;

    public function getDescription(): string;
}
