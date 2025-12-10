<?php

declare(strict_types=1);

// ABOUTME: Interface for UI components that can render to console output.
// ABOUTME: Used by widgets and other visual elements.

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
