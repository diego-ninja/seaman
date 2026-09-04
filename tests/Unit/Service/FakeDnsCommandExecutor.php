<?php

declare(strict_types=1);

// ABOUTME: Deterministic command executor fixture for DNS manager tests.
// ABOUTME: Models installed providers and occupied DNS ports without shell commands.

namespace Seaman\Tests\Unit\Service;

use Seaman\Contract\CommandExecutor;
use Seaman\ValueObject\ProcessResult;

final readonly class FakeDnsCommandExecutor implements CommandExecutor
{
    public function __construct(
        private bool $hasDnsmasq = false,
        private bool $hasSystemdResolved = false,
        private bool $hasNetworkManager = false,
        private bool $isDnsmasqRunning = false,
        private bool $isPort53Occupied = false,
    ) {}

    public function execute(array $command): ProcessResult
    {
        if ($command[0] === 'which' && $command[1] === 'dnsmasq') {
            return new ProcessResult(exitCode: $this->hasDnsmasq ? 0 : 1);
        }

        if ($command[0] === 'systemctl' && $command[1] === 'is-active') {
            return match ($command[2]) {
                'systemd-resolved' => new ProcessResult(exitCode: $this->hasSystemdResolved ? 0 : 1),
                'NetworkManager' => new ProcessResult(exitCode: $this->hasNetworkManager ? 0 : 1),
                'dnsmasq' => new ProcessResult(exitCode: $this->isDnsmasqRunning ? 0 : 1),
                default => new ProcessResult(exitCode: 0),
            };
        }

        if ($command[0] === 'ss' && in_array('-tlnp', $command, true)) {
            return new ProcessResult(exitCode: 0, output: $this->isPort53Occupied ? '127.0.0.53:53' : '');
        }

        return new ProcessResult(exitCode: 0);
    }
}
