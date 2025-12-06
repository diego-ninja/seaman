<?php

declare(strict_types=1);

// ABOUTME: Advanced table widget with full-width header lines support.
// ABOUTME: Renders tables with Unicode box-drawing characters and colors.

namespace Seaman\UI\Widget\Table;

use Laravel\Prompts\Prompt;

final class Table extends Prompt
{
    /**
     * Marker class for row separators.
     */
    private const string SEPARATOR_MARKER = '__TABLE_SEPARATOR__';

    /**
     * @param list<string> $headerLines Full-width lines above column headers
     * @param list<string> $headers Column headers
     * @param list<list<string|list<string>>|string> $rows Table rows (string = separator marker)
     */
    public function __construct(
        public array $headerLines = [],
        public array $headers = [],
        public array $rows = [],
    ) {}

    /**
     * Create a row separator marker.
     */
    public static function separator(): string
    {
        return self::SEPARATOR_MARKER;
    }

    /**
     * Check if a row is a separator.
     *
     * @param list<string|list<string>>|string $row
     */
    public static function isSeparator(array|string $row): bool
    {
        return $row === self::SEPARATOR_MARKER;
    }

    /**
     * Display the table.
     */
    public function display(): void
    {
        $this->prompt();
    }

    /**
     * Render and display the table.
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
     * Render the table using the custom renderer.
     */
    protected function renderTheme(): string
    {
        $renderer = new TableRenderer($this);

        return $renderer($this);
    }
}
