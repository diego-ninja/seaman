<?php

// ABOUTME: Renders BoxedOutput prompt using box-drawing characters.
// ABOUTME: Uses DrawsBoxes trait for consistent border styling.

declare(strict_types=1);

namespace Seaman\UI\Widget\Box;

use Laravel\Prompts\Themes\Default\Renderer;
use Seaman\UI\Widget\Box\Concerns\DrawsBoxes;

final class BoxRenderer extends Renderer
{
    use DrawsBoxes;

    /**
     * Render the boxed output.
     */
    public function __invoke(Box $output): string
    {
        $this->box(
            title: $output->title,
            body: $output->message,
            footer: $output->footer,
            color: $output->color,
            info: $output->info,
        );

        return (string) $this;
    }
}
