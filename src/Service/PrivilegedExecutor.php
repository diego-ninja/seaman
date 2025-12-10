<?php

declare(strict_types=1);

// ABOUTME: Executes commands with elevated privileges using pkexec or sudo.
// ABOUTME: Prefers pkexec when available, falls back to sudo.

namespace Seaman\Service;

use Seaman\Contract\CommandExecutor;
use Seaman\ValueObject\ProcessResult;

final class PrivilegedExecutor
{
    private ?bool $hasPkexec = null;

    public function __construct(
        private readonly CommandExecutor $executor,
    ) {}

    /**
     * Execute a command with elevated privileges.
     *
     * @param list<string> $command Command and arguments (without sudo/pkexec prefix)
     */
    public function execute(array $command): ProcessResult
    {
        $privilegedCommand = $this->prependPrivilegeEscalation($command);

        return $this->executor->execute($privilegedCommand);
    }

    /**
     * Prepend the appropriate privilege escalation command.
     *
     * @param list<string> $command
     * @return list<string>
     */
    public function prependPrivilegeEscalation(array $command): array
    {
        $escalation = $this->getPrivilegeEscalationCommand();

        return array_merge($escalation, $command);
    }

    /**
     * Get the privilege escalation command prefix.
     *
     * @return list<string>
     */
    public function getPrivilegeEscalationCommand(): array
    {
        return $this->hasPkexec() ? ['pkexec'] : ['sudo'];
    }

    /**
     * Get the privilege escalation command as a string for display.
     */
    public function getPrivilegeEscalationString(): string
    {
        return $this->hasPkexec() ? 'pkexec' : 'sudo';
    }

    /**
     * Build a privileged command string for display or instructions.
     *
     * @param list<string>|string $command Command as array or string
     */
    public function buildPrivilegedCommandString(array|string $command): string
    {
        $prefix = $this->getPrivilegeEscalationString();
        $commandStr = is_array($command) ? implode(' ', $command) : $command;

        return "{$prefix} {$commandStr}";
    }

    /**
     * Check if pkexec is available on the system.
     */
    public function hasPkexec(): bool
    {
        if ($this->hasPkexec !== null) {
            return $this->hasPkexec;
        }

        $result = $this->executor->execute(['which', 'pkexec']);
        $this->hasPkexec = $result->isSuccessful() && trim($result->output) !== '';

        return $this->hasPkexec;
    }

    /**
     * Reset the cached pkexec detection (useful for testing).
     */
    public function resetCache(): void
    {
        $this->hasPkexec = null;
    }
}
