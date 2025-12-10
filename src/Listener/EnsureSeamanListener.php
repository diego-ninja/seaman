<?php

declare(strict_types=1);

// ABOUTME: Event listener that validates Seaman environment before command execution.
// ABOUTME: Ensures Terminal output is properly initialized.

namespace Seaman\Listener;

use Seaman\Attribute\AsEventListener;
use Seaman\Command\AbstractSeamanCommand;
use Seaman\UI\Terminal;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;

#[AsEventListener(event: ConsoleEvents::COMMAND, priority: 100)]
final class EnsureSeamanListener
{
    /**
     * @var list<string>
     */
    private array $excluded = [
        'seaman:init',
        'seaman:build',
    ];
    public function __invoke(ConsoleCommandEvent $event): void
    {
        $projectRoot = (string) getcwd();
        $commandName = $event->getCommand();
        if (!$this->isExcluded($commandName)) {
            if (!file_exists($projectRoot . '/.seaman/seaman.yaml')) {
                Terminal::error('seaman.yaml not found. Run "seaman init" first.');
                exit(Command::FAILURE);
            }
        }
    }

    private function isExcluded(?Command $command): bool
    {
        if (!$command instanceof AbstractSeamanCommand) {
            return true;
        }

        return in_array($command->getName(), $this->excluded, true);
    }
}
