<?php

declare(strict_types=1);

namespace Seaman\UI\Widget\Table\Manipulator;

use Seaman\UI\Widget\Table\Manipulator\Contract\TableManipulator;

class TimeManipulator implements TableManipulator
{
    final public const TYPE = 'time';

    public function manipulate(mixed $value): ?string
    {
        if (!$value) {
            return 'Not Recorded';
        }

        return date('g:i a', $value);
    }
}
