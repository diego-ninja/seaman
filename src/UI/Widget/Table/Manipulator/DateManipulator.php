<?php

declare(strict_types=1);

namespace Seaman\UI\Widget\Table\Manipulator;

use Seaman\UI\Widget\Table\Manipulator\Contract\TableManipulator;

class DateManipulator implements TableManipulator
{
    final public const TYPE = 'date';

    public function manipulate(mixed $value): ?string
    {
        if (!$value) {
            return 'Not Recorded';
        }
        return date('d-m-Y', $value);
    }
}
