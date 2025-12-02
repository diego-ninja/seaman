<?php

declare(strict_types=1);

namespace Seaman\UI\Widget\Table\Manipulator;

use RuntimeException;
use Seaman\UI\Widget\Table\Manipulator\Contract\TableManipulator;

class ManipulatorFactory
{
    /**
     * @throws RuntimeException
     */
    public static function create(string $type): TableManipulator
    {
        $manipulatorClass = 'Ninja\\Cosmic\\Terminal\\Table\\Manipulator\\' . ucfirst($type) . 'Manipulator';

        if (!class_exists($manipulatorClass)) {
            throw new RuntimeException('Manipulator class ' . $manipulatorClass . ' does not exist.');
        }

        /** @var TableManipulator $manipulator */
        $manipulator = new $manipulatorClass();
        return $manipulator;
    }
}
