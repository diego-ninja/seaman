<?php

declare(strict_types=1);

namespace Seaman\UI\Widget\Table\Manipulator;

use Seaman\UI\Widget\Table\Manipulator\Contract\TableManipulator;

class PercentManipulator implements TableManipulator
{
    final public const TYPE = 'percent';

    public function manipulate(mixed $value): ?string
    {
        if (!$value) {
            return '';
        }

        return number_format($value, 2) . '%';
    }
}
