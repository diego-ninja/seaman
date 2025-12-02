<?php

declare(strict_types=1);

namespace Seaman\UI\Widget\Table\Manipulator;

use Seaman\UI\Widget\Table\Manipulator\Contract\TableManipulator;

class NicetimeManipulator implements TableManipulator
{
    final public const TYPE = 'nicetime';

    public function manipulate(mixed $value): ?string
    {
        if (!$value) {
            return '';
        }

        if ($value > mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('Y'))) {
            return 'Today ' . date('g:i a', $value);
        }

        if ($value > mktime(0, 0, 0, (int) date('m'), (int) date('d') - 1, (int) date('Y'))) {
            return 'Yesterday ' . date('g:i a', $value);
        }

        return date('jS F Y, g:i a', $value);
    }
}
