<?php

// ABOUTME: Sends OS notification when command fails.
// ABOUTME: Uses Notifier service for desktop notifications.

declare(strict_types=1);

namespace Seaman\Listener;

use Seaman\Attribute\AsEventListener;
use Seaman\Notifier\Notifier;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\ConsoleEvents;

#[AsEventListener(event: ConsoleEvents::ERROR, priority: 0)]
final readonly class NotifyOnErrorListener
{
    public function __invoke(ConsoleErrorEvent $event): void
    {
        $error = $event->getError();
        $command = $event->getCommand();

        Notifier::error(sprintf(
            'Command "%s" failed: %s',
            $command?->getName() ?? 'unknown',
            $error->getMessage(),
        ));
    }
}
