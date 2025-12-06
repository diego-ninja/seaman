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
     * @var list<string>
     */
    private array $headerLines = [];

    /**
     * @var list<string>
     */
    private array $columnHeaders = [];

    /**
     * @var list<list<string|list<string>>|string>
     */
    private array $rows = [];

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
     * Add a header line (full-width, above column headers).
     *
     * @return $this
     */
    public function addHeaderLine(string $line): self
    {
        $this->headerLines[] = $line;
        return $this;
    }

    /**
     * Set column headers.
     *
     * @param list<string> $headers
     * @return $this
     */
    public function setHeaders(array $headers): self
    {
        $this->columnHeaders = $headers;
        return $this;
    }

    /**
     * Add a data row.
     *
     * @param list<string|list<string>> $row
     * @return $this
     */
    public function addRow(array $row): self
    {
        $this->rows[] = $row;
        return $this;
    }

    /**
     * Add a separator row.
     *
     * @return $this
     */
    public function addSeparator(): self
    {
        $this->rows[] = self::SEPARATOR_MARKER;
        return $this;
    }

    /**
     * Get header lines.
     *
     * @return list<string>
     */
    public function getHeaderLines(): array
    {
        return $this->headerLines;
    }

    /**
     * Get column headers.
     *
     * @return list<string>
     */
    public function getColumnHeaders(): array
    {
        return $this->columnHeaders;
    }

    /**
     * Get rows.
     *
     * @return list<list<string|list<string>>|string>
     */
    public function getRows(): array
    {
        return $this->rows;
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
