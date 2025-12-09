<?php

// ABOUTME: Tests for OperatingMode enum.
// ABOUTME: Validates enum cases and behavior.

declare(strict_types=1);

namespace Tests\Unit\Enum;

use Seaman\Enum\OperatingMode;
use PHPUnit\Framework\TestCase;

final class OperatingModeTest extends TestCase
{
    public function test_has_managed_case(): void
    {
        $this->assertSame('Managed', OperatingMode::Managed->name);
    }

    public function test_has_unmanaged_case(): void
    {
        $this->assertSame('Unmanaged', OperatingMode::Unmanaged->name);
    }

    public function test_has_uninitialized_case(): void
    {
        $this->assertSame('Uninitialized', OperatingMode::Uninitialized->name);
    }

    public function test_managed_requires_initialization(): void
    {
        $this->assertFalse(OperatingMode::Managed->requiresInitialization());
    }

    public function test_unmanaged_does_not_require_initialization(): void
    {
        $this->assertFalse(OperatingMode::Unmanaged->requiresInitialization());
    }

    public function test_uninitialized_requires_initialization(): void
    {
        $this->assertTrue(OperatingMode::Uninitialized->requiresInitialization());
    }

    public function test_managed_is_managed_mode(): void
    {
        $this->assertTrue(OperatingMode::Managed->isManaged());
        $this->assertFalse(OperatingMode::Unmanaged->isManaged());
        $this->assertFalse(OperatingMode::Uninitialized->isManaged());
    }
}
