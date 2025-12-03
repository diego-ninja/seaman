<?php

declare(strict_types=1);

namespace Seaman\Listener;

use Seaman\Attribute\AsEventListener;
use Seaman\Contract\Decorable;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;

use function Termwind\render;
use function Termwind\terminal;

#[AsEventListener(event: ConsoleEvents::COMMAND, priority: 100)]
final readonly class CommandDecorationListener
{
    public function __invoke(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        $version = $command?->getApplication()?->getVersion() ?? 'Unreleased';

        if ($command instanceof Decorable) {
            $width = 134;
            $half = $width / 2;

            $maxWidth = terminal()->width();
            if ($maxWidth <= $width) {
                $width = terminal()->width();
                $half = terminal()->width() / 2;
            }

            render('<br />');
            render("<div class='w-{$width}'><span class='w-{$half} text-left'>🔱 Seaman v{$version}</span><span class='w-{$half} text-right text-cyan'>" . $command->getName() . "</span><hr class='text-blue'></div>");
        }
    }
}
