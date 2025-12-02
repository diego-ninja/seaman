<?php

declare(strict_types=1);

namespace Seaman\UI\Widget\Table\Manipulator;

use Seaman\UI\Widget\Table\Manipulator\Contract\TableManipulator;

class DollarManipulator implements TableManipulator
{
    final public const TYPE = 'dollar';

    public function manipulate(mixed $value): ?string
    {
        return '$' . number_format($value, 2);
    }
}
