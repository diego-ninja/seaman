<?php

declare(strict_types=1);

namespace Seaman\Command;

use Seaman\Notifier\NotifiableInterface;
use Seaman\Notifier\Notifier;
use Seaman\UI\Terminal;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractSeamanCommand extends Command
{
    public function run(InputInterface $input, OutputInterface $output): int
    {
        Terminal::setOutput($output);
        try {
            return parent::run($input, $output);
        } finally {
            Terminal::resetOutput();
        }
    }

    protected function success(): int
    {
        if ($this instanceof NotifiableInterface) {
            Notifier::success($this->getSuccessMessage());
        }

        return Command::SUCCESS;
    }

    protected function failure(): int
    {
        if ($this instanceof NotifiableInterface) {
            Notifier::error($this->getErrorMessage());
        }

        return Command::FAILURE;
    }
}
