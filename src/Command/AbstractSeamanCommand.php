<?php

declare(strict_types=1);

namespace Seaman\Command;

use Seaman\Notifier\NotifiableInterface;
use Seaman\Notifier\Notifier;
use Symfony\Component\Console\Command\Command;

abstract class AbstractSeamanCommand extends Command
{
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
