<?php

declare(strict_types=1);

namespace Seaman\UI\Widget\Table\Manipulator;

use Ramsey\Collection\AbstractCollection;
use Seaman\UI\Widget\Table\Manipulator\Contract\TableManipulator;

/**
 * @extends AbstractCollection<TableManipulator>
 */
class ManipulatorCollection extends AbstractCollection
{
    public function getType(): string
    {
        return TableManipulator::class;
    }

    public function apply(mixed $value): string
    {
        foreach ($this->data as $manipulator) {
            $value = $manipulator->manipulate($value);
        }

        return $value;
    }
}
