<?php

declare(strict_types=1);

namespace Seaman\UI\Widget\Table\Manipulator;

use Seaman\UI\Widget\Table\Manipulator\Contract\TableManipulator;

class BoolManipulator implements TableManipulator
{
    final public const string TYPE = 'bool';

    public function manipulate(mixed $value): ?string
    {
        if (!is_bool($value)) {
            return $value;
        }

        return $value ? 'true' : 'false';
    }
}
