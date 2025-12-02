<?php

declare(strict_types=1);

namespace Seaman\UI\Widget\Table\Manipulator\Contract;

interface TableManipulator
{
    public function manipulate(mixed $value): ?string;
}
