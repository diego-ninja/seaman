<?php

declare(strict_types=1);

namespace Seaman\UI\Widget\Table\Column;

use Seaman\UI\Widget\Table\Column\Contract\TableColumn;
use Seaman\UI\Widget\Table\Manipulator\Contract\TableManipulator;
use Seaman\UI\Widget\Table\Manipulator\ManipulatorCollection;
use Seaman\UI\Widget\Table\TableConfig;

readonly class Column implements TableColumn
{
    private ManipulatorCollection $manipulators;

    public function __construct(
        public string  $name,
        public string  $key,
        public ?string $color = TableConfig::DEFAULT_FIELD_COLOR,
    ) {
        $this->manipulators = new ManipulatorCollection();
    }

    public static function create(
        string $name,
        string $key,
        ?string $color,
    ): self {
        return new self($name, $key, $color);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function getManipulators(): ManipulatorCollection
    {
        return $this->manipulators;
    }

    public function addManipulator(TableManipulator $manipulator): self
    {
        $this->manipulators->add($manipulator);
        return $this;
    }
}
