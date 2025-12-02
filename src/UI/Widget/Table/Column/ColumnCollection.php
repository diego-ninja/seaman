<?php

declare(strict_types=1);

namespace Seaman\UI\Widget\Table\Column;

use Ramsey\Collection\AbstractCollection;

/**
 * @extends AbstractCollection<Column>
 */
class ColumnCollection extends AbstractCollection
{
    public function getType(): string
    {
        return Column::class;
    }

    public function getByKey(string $key): ?Column
    {
        foreach ($this->getIterator() as $item) {
            if ($item->key === $key) {
                return $item;
            }
        }

        return null;
    }

    public function getByName(string $name): ?Column
    {
        foreach ($this->getIterator() as $item) {
            if ($item->name === $name) {
                return $item;
            }
        }

        return null;
    }

}
