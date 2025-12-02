<?php

declare(strict_types=1);

namespace Seaman\UI\Widget\Table\Manipulator;

use Seaman\UI\Widget\Table\Manipulator\Contract\TableManipulator;

class DatetimeManipulator implements TableManipulator
{
    final public const TYPE = 'datetime';

    public function manipulate(mixed $value): ?string
    {
        if (!$value) {
            return 'Not Recorded';
        }

        return date('jS F Y, g:i a', $value);
    }
}
