<?php

// ABOUTME: Logs command execution to error log.
// ABOUTME: Executes before all commands with high priority.

declare(strict_types=1);

namespace Seaman\Listener;

use Seaman\Attribute\AsEventListener;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\ConsoleEvents;

#[AsEventListener(event: ConsoleEvents::COMMAND, priority: 100)]
final readonly class LogCommandListener
{
    public function __invoke(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();

        error_log(sprintf(
            '[Seaman] Executing command: %s',
            $command?->getName() ?? 'unknown',
        ));
    }
}
