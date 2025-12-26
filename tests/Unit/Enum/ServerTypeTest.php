<?php

// ABOUTME: Tests for ServerType enum.
// ABOUTME: Validates server type cases and helper methods.

declare(strict_types=1);

namespace Tests\Unit\Enum;

use PHPUnit\Framework\TestCase;
use Seaman\Enum\ServerType;

final class ServerTypeTest extends TestCase
{
    public function test_has_symfony_server_case(): void
    {
        $this->assertSame('symfony', ServerType::SymfonyServer->value);
    }

    public function test_has_frankenphp_classic_case(): void
    {
        $this->assertSame('frankenphp', ServerType::FrankenPhpClassic->value);
    }

    public function test_has_frankenphp_worker_case(): void
    {
        $this->assertSame('frankenphp-worker', ServerType::FrankenPhpWorker->value);
    }

    public function test_get_label_returns_human_readable_name(): void
    {
        $this->assertSame('Symfony Server', ServerType::SymfonyServer->getLabel());
        $this->assertSame('FrankenPHP', ServerType::FrankenPhpClassic->getLabel());
        $this->assertSame('FrankenPHP Worker', ServerType::FrankenPhpWorker->getLabel());
    }

    public function test_get_description_returns_description(): void
    {
        $this->assertSame('Built-in development server', ServerType::SymfonyServer->getDescription());
        $this->assertSame('Modern PHP server with Caddy', ServerType::FrankenPhpClassic->getDescription());
        $this->assertSame('Persistent process (advanced)', ServerType::FrankenPhpWorker->getDescription());
    }

    public function test_is_frankenphp_returns_correct_value(): void
    {
        $this->assertFalse(ServerType::SymfonyServer->isFrankenPhp());
        $this->assertTrue(ServerType::FrankenPhpClassic->isFrankenPhp());
        $this->assertTrue(ServerType::FrankenPhpWorker->isFrankenPhp());
    }

    public function test_is_worker_mode_returns_correct_value(): void
    {
        $this->assertFalse(ServerType::SymfonyServer->isWorkerMode());
        $this->assertFalse(ServerType::FrankenPhpClassic->isWorkerMode());
        $this->assertTrue(ServerType::FrankenPhpWorker->isWorkerMode());
    }
}
