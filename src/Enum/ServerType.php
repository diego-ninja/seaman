<?php

// ABOUTME: Server type enum for application serving.
// ABOUTME: Supports Symfony Server and FrankenPHP modes.

declare(strict_types=1);

namespace Seaman\Enum;

enum ServerType: string
{
    case SymfonyServer = 'symfony';
    case FrankenPhpClassic = 'frankenphp';
    case FrankenPhpWorker = 'frankenphp-worker';

    public function getLabel(): string
    {
        return match ($this) {
            self::SymfonyServer => 'Symfony Server',
            self::FrankenPhpClassic => 'FrankenPHP',
            self::FrankenPhpWorker => 'FrankenPHP Worker',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::SymfonyServer => 'Built-in development server',
            self::FrankenPhpClassic => 'Modern PHP server with Caddy',
            self::FrankenPhpWorker => 'Persistent process (advanced)',
        };
    }

    public function isFrankenPhp(): bool
    {
        return $this !== self::SymfonyServer;
    }

    public function isWorkerMode(): bool
    {
        return $this === self::FrankenPhpWorker;
    }
}
