<?php

declare(strict_types=1);

namespace Seaman\Listener;

use Seaman\Attribute\AsEventListener;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;

use function Termwind\render;

#[AsEventListener(event: ConsoleEvents::COMMAND, priority: 100)]
final readonly class CommandDecorationListener
{
    public function __invoke(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        render('<br />');
        render("<div class='w-100'><span class='w-50 text-left'>🔱 Seaman v1.0.0-beta</span><span class='w-50 text-right text-cyan'>" . $command?->getName() . "</span><hr class='text-blue'></div>");
    }
}
