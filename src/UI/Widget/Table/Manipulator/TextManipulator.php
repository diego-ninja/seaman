<?php

declare(strict_types=1);

namespace Seaman\UI\Widget\Table\Manipulator;

use Seaman\UI\Widget\Table\Manipulator\Contract\TableManipulator;

class TextManipulator implements TableManipulator
{
    final public const TYPE = 'text';

    public function manipulate(mixed $value): ?string
    {
        if (!$value) {
            return '';
        }

        return strip_tags((string) $value);
    }
}
