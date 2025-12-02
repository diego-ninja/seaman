<?php

declare(strict_types=1);

namespace Seaman\UI\Widget\Table\Manipulator;

use Seaman\UI\Widget\Table\Manipulator\Contract\TableManipulator;

class DatelongManipulator implements TableManipulator
{
    final public const TYPE = 'datelong';

    public function manipulate(mixed $value): ?string
    {
        if (!$value) {
            return 'Not Recorded';
        }
        return date('d-m-Y H:i:s', $value);
    }
}
