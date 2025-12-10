<?php

declare(strict_types=1);

// ABOUTME: Tests for PrivilegedExecutor service.
// ABOUTME: Validates pkexec/sudo detection and command building.

namespace Seaman\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Seaman\Contract\CommandExecutor;
use Seaman\Service\PrivilegedExecutor;
use Seaman\ValueObject\ProcessResult;

final class PrivilegedExecutorTest extends TestCase
{
    #[Test]
    public function it_uses_pkexec_when_available(): void
    {
        $executor = $this->createMock(CommandExecutor::class);
        $executor->expects($this->once())
            ->method('execute')
            ->with(['which', 'pkexec'])
            ->willReturn(new ProcessResult(0, '/usr/bin/pkexec', ''));

        $privilegedExecutor = new PrivilegedExecutor($executor);

        $this->assertTrue($privilegedExecutor->hasPkexec());
        $this->assertEquals(['pkexec'], $privilegedExecutor->getPrivilegeEscalationCommand());
        $this->assertEquals('pkexec', $privilegedExecutor->getPrivilegeEscalationString());
    }

    #[Test]
    public function it_falls_back_to_sudo_when_pkexec_unavailable(): void
    {
        $executor = $this->createMock(CommandExecutor::class);
        $executor->expects($this->once())
            ->method('execute')
            ->with(['which', 'pkexec'])
            ->willReturn(new ProcessResult(1, '', 'pkexec not found'));

        $privilegedExecutor = new PrivilegedExecutor($executor);

        $this->assertFalse($privilegedExecutor->hasPkexec());
        $this->assertEquals(['sudo'], $privilegedExecutor->getPrivilegeEscalationCommand());
        $this->assertEquals('sudo', $privilegedExecutor->getPrivilegeEscalationString());
    }

    #[Test]
    public function it_caches_pkexec_detection(): void
    {
        $executor = $this->createMock(CommandExecutor::class);
        $executor->expects($this->once())
            ->method('execute')
            ->with(['which', 'pkexec'])
            ->willReturn(new ProcessResult(0, '/usr/bin/pkexec', ''));

        $privilegedExecutor = new PrivilegedExecutor($executor);

        // Call multiple times - should only check once
        $privilegedExecutor->hasPkexec();
        $privilegedExecutor->hasPkexec();
        $privilegedExecutor->hasPkexec();

        $this->assertTrue($privilegedExecutor->hasPkexec());
    }

    #[Test]
    public function it_prepends_pkexec_to_command(): void
    {
        $executor = $this->createMock(CommandExecutor::class);
        $executor->method('execute')
            ->with(['which', 'pkexec'])
            ->willReturn(new ProcessResult(0, '/usr/bin/pkexec', ''));

        $privilegedExecutor = new PrivilegedExecutor($executor);

        $result = $privilegedExecutor->prependPrivilegeEscalation(['cp', '/tmp/file', '/etc/hosts']);

        $this->assertEquals(['pkexec', 'cp', '/tmp/file', '/etc/hosts'], $result);
    }

    #[Test]
    public function it_prepends_sudo_to_command_when_pkexec_unavailable(): void
    {
        $executor = $this->createMock(CommandExecutor::class);
        $executor->method('execute')
            ->with(['which', 'pkexec'])
            ->willReturn(new ProcessResult(1, '', ''));

        $privilegedExecutor = new PrivilegedExecutor($executor);

        $result = $privilegedExecutor->prependPrivilegeEscalation(['rm', '-f', '/etc/config']);

        $this->assertEquals(['sudo', 'rm', '-f', '/etc/config'], $result);
    }

    #[Test]
    public function it_builds_privileged_command_string_from_array(): void
    {
        $executor = $this->createMock(CommandExecutor::class);
        $executor->method('execute')
            ->with(['which', 'pkexec'])
            ->willReturn(new ProcessResult(0, '/usr/bin/pkexec', ''));

        $privilegedExecutor = new PrivilegedExecutor($executor);

        $result = $privilegedExecutor->buildPrivilegedCommandString(['systemctl', 'restart', 'dnsmasq']);

        $this->assertEquals('pkexec systemctl restart dnsmasq', $result);
    }

    #[Test]
    public function it_builds_privileged_command_string_from_string(): void
    {
        $executor = $this->createMock(CommandExecutor::class);
        $executor->method('execute')
            ->with(['which', 'pkexec'])
            ->willReturn(new ProcessResult(1, '', ''));

        $privilegedExecutor = new PrivilegedExecutor($executor);

        $result = $privilegedExecutor->buildPrivilegedCommandString('brew services restart dnsmasq');

        $this->assertEquals('sudo brew services restart dnsmasq', $result);
    }

    #[Test]
    public function it_executes_command_with_privilege_escalation(): void
    {
        $executor = $this->createMock(CommandExecutor::class);
        $executor->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function (array $command): ProcessResult {
                if ($command === ['which', 'pkexec']) {
                    return new ProcessResult(0, '/usr/bin/pkexec', '');
                }
                if ($command === ['pkexec', 'cp', '/tmp/file', '/etc/hosts']) {
                    return new ProcessResult(0, '', '');
                }
                $this->fail('Unexpected command: ' . implode(' ', $command));
            });

        $privilegedExecutor = new PrivilegedExecutor($executor);

        $result = $privilegedExecutor->execute(['cp', '/tmp/file', '/etc/hosts']);

        $this->assertTrue($result->isSuccessful());
    }

    #[Test]
    public function it_resets_cache_when_requested(): void
    {
        $executor = $this->createMock(CommandExecutor::class);
        $executor->expects($this->exactly(2))
            ->method('execute')
            ->with(['which', 'pkexec'])
            ->willReturnOnConsecutiveCalls(
                new ProcessResult(0, '/usr/bin/pkexec', ''),
                new ProcessResult(1, '', ''),
            );

        $privilegedExecutor = new PrivilegedExecutor($executor);

        $this->assertTrue($privilegedExecutor->hasPkexec());

        $privilegedExecutor->resetCache();

        $this->assertFalse($privilegedExecutor->hasPkexec());
    }

    #[Test]
    public function it_handles_empty_which_output_as_not_found(): void
    {
        $executor = $this->createMock(CommandExecutor::class);
        $executor->method('execute')
            ->with(['which', 'pkexec'])
            ->willReturn(new ProcessResult(0, '', ''));

        $privilegedExecutor = new PrivilegedExecutor($executor);

        $this->assertFalse($privilegedExecutor->hasPkexec());
    }
}
