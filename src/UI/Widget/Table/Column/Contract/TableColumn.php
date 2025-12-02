<?php

declare(strict_types=1);

namespace Seaman\UI\Widget\Table\Column\Contract;

use Seaman\UI\Widget\Table\Manipulator\Contract\TableManipulator;
use Seaman\UI\Widget\Table\Manipulator\ManipulatorCollection;

interface TableColumn
{
    public function getName(): string;
    public function getKey(): string;
    public function getManipulators(): ManipulatorCollection;
    public function addManipulator(TableManipulator $manipulator): self;
    public function getColor(): ?string;
}
