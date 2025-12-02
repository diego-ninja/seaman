<?php

declare(strict_types=1);

namespace Seaman\UI\Contract;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Interface RenderableInterface
 * @template T
 */
interface Renderable
{
    public function render(OutputInterface $output): void;
}
