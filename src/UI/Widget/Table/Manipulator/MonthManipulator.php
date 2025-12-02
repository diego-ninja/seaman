<?php

declare(strict_types=1);

namespace Seaman\UI\Widget\Table\Manipulator;

use Seaman\UI\Widget\Table\Manipulator\Contract\TableManipulator;

class MonthManipulator implements TableManipulator
{
    final public const TYPE = 'month';

    public function manipulate(mixed $value): ?string
    {
        if (!$value) {
            return 'Not Recorded';
        }

        return date('F', $value);
    }
}
