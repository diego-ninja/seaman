<?php

declare(strict_types=1);

namespace Seaman\Service\Container;

use Seaman\Enum\Service;

abstract readonly class AbstractService implements ServiceInterface
{
    abstract public function getType(): Service;

    public function getName(): string
    {
        return $this->getType()->value;
    }

    public function getDisplayName(): string
    {
        return $this->getType()->name;
    }

    public function getDescription(): string
    {
        return $this->getType()->description();
    }

    public function getIcon(): string
    {
        return '⚙';
    }

}
