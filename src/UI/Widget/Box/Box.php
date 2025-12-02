<?php

// ABOUTME: Output-only prompt component that displays text in a bordered box.
// ABOUTME: Renders immediately without waiting for user input.

declare(strict_types=1);

namespace Seaman\UI\Widget\Box;

use Laravel\Prompts\Prompt;

final class Box extends Prompt
{
    /**
     * Create a new BoxedOutput instance.
     */
    public function __construct(
        public string $title,
        public string $message,
        public string $footer = '',
        public string $color = 'gray',
        public string $info = '',
    ) {}

    /**
     * Display the boxed output.
     */
    public function display(): void
    {
        $this->prompt();
    }

    /**
     * Render the boxed output.
     */
    public function prompt(): bool
    {
        $this->capturePreviousNewLines();
        $this->state = 'submit';
        static::output()->write($this->renderTheme());

        return true;
    }

    /**
     * Get the value of the prompt.
     */
    public function value(): bool
    {
        return true;
    }

    /**
     * Render the prompt using the custom renderer.
     */
    protected function renderTheme(): string
    {
        $renderer = new BoxRenderer($this);

        return $renderer($this);
    }
}
