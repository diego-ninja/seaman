<?php

declare(strict_types=1);

namespace Seaman\UI\Widget\Table\Manipulator;

use Seaman\UI\Widget\Table\Manipulator\Contract\TableManipulator;

class YearManipulator implements TableManipulator
{
    final public const string TYPE = 'year';

    public function manipulate(mixed $value): ?string
    {
        if (!$value) {
            return 'Not Recorded';
        }

        return date('Y', $value);
    }
}
