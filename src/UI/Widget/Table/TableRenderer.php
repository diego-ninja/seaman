<?php

declare(strict_types=1);

// ABOUTME: Renders Table widget with Unicode box-drawing and full-width headers.
// ABOUTME: Uses Symfony Table internally with custom header section rendering.

namespace Seaman\UI\Widget\Table;

use Laravel\Prompts\Output\BufferedConsoleOutput;
use Laravel\Prompts\Themes\Default\Renderer;
use Seaman\UI\Widget\Box\Concerns\InteractsWithStrings;
use Symfony\Component\Console\Helper\Table as SymfonyTable;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Helper\TableStyle;

final class TableRenderer extends Renderer
{
    use InteractsWithStrings;

    /**
     * Render the table.
     */
    public function __invoke(Table $table): string
    {
        $headerLines = $table->getHeaderLines();
        $columnHeaders = $table->getColumnHeaders();
        $rows = $table->getRows();

        // Render the data table first to get its width
        $dataOutput = $this->renderDataTable($columnHeaders, $rows);
        $lines = explode(PHP_EOL, trim($dataOutput));

        if ($lines === ['']) {
            return (string) $this;
        }

        // Calculate table width from rendered output
        $tableWidth = mb_strwidth($this->stripEscapeSequences($lines[0]));

        // If we have header lines, check if they need more width
        if (count($headerLines) > 0) {
            // +4 for border and padding on each side
            $headerContentWidth = $this->longest($headerLines, 4);
            $requiredTableWidth = $headerContentWidth + 2; // +2 for borders

            if ($requiredTableWidth > $tableWidth) {
                // Re-render table with minimum column widths to accommodate header
                $dataOutput = $this->renderDataTableWithMinWidth($columnHeaders, $rows, $requiredTableWidth);
                $lines = explode(PHP_EOL, trim($dataOutput));
                $tableWidth = mb_strwidth($this->stripEscapeSequences($lines[0]));
            }

            $this->renderHeaderSection($headerLines, $tableWidth);
            $this->renderDataWithConnector($lines);
        } else {
            foreach ($lines as $line) {
                $this->line(' ' . $this->colorBorderCharacters($line));
            }
        }

        return (string) $this;
    }

    /**
     * Render the data table using Symfony Table.
     *
     * @param list<string> $headers
     * @param list<list<string|list<string>>|string> $rows
     */
    private function renderDataTable(array $headers, array $rows): string
    {
        return $this->renderDataTableWithMinWidth($headers, $rows, 0);
    }

    /**
     * Render the data table with a minimum total width.
     *
     * @param list<string> $headers
     * @param list<list<string|list<string>>|string> $rows
     */
    private function renderDataTableWithMinWidth(array $headers, array $rows, int $minWidth): string
    {
        $style = $this->createTableStyle();

        $buffered = new BufferedConsoleOutput();
        $symfonyTable = new SymfonyTable($buffered);
        $symfonyTable->setStyle($style);

        if (count($headers) > 0) {
            $symfonyTable->setHeaders($headers);

            // If we need extra width, distribute it to the last column
            if ($minWidth > 0) {
                $columnCount = count($headers);
                // Approximate current width per column (rough estimate)
                // We'll set minimum width on the last column to absorb extra space
                $extraWidth = $minWidth - ($columnCount * 10); // rough base
                if ($extraWidth > 0) {
                    $symfonyTable->setColumnWidth($columnCount - 1, (int) ($extraWidth / 2));
                }
            }
        }

        foreach ($rows as $row) {
            if (Table::isSeparator($row)) {
                $symfonyTable->addRow(new TableSeparator());
            } else {
                /** @var list<string|list<string>> $row */
                $symfonyTable->addRow($this->normalizeRow($row));
            }
        }

        $symfonyTable->render();

        return $buffered->content();
    }

    /**
     * Create the table style with Unicode box-drawing characters.
     * No color formatting - colors are applied during post-processing.
     */
    private function createTableStyle(): TableStyle
    {
        return (new TableStyle())
            ->setHorizontalBorderChars('─')
            ->setVerticalBorderChars('│', '│')
            ->setCrossingChars('┼', '┌', '┬', '┐', '┤', '┘', '┴', '└', '├')
            ->setCellHeaderFormat('<options=bold>%s</>')
            ->setCellRowFormat('%s');
    }

    /**
     * Normalize a row, converting array cells to multi-line strings.
     *
     * @param list<string|list<string>> $row
     * @return list<string>
     */
    private function normalizeRow(array $row): array
    {
        return array_map(function (string|array $cell): string {
            if (is_array($cell)) {
                return implode("\n", $cell);
            }
            return $cell;
        }, $row);
    }

    /**
     * Render the header section with full-width lines.
     *
     * @param list<string> $headerLines
     */
    private function renderHeaderSection(array $headerLines, int $tableWidth): void
    {
        // Content width is table width minus the two border characters
        $contentWidth = $tableWidth - 2;

        // Top border
        $this->line(' ' . $this->gray('┌' . str_repeat('─', $contentWidth) . '┐'));

        // Header lines with padding
        foreach ($headerLines as $line) {
            $paddedLine = ' ' . $this->pad($line, $contentWidth - 1);
            $this->line(' ' . $this->gray('│') . $paddedLine . $this->gray('│'));
        }
    }

    /**
     * Render data table lines with connector and gray borders.
     *
     * @param list<string> $lines
     */
    private function renderDataWithConnector(array $lines): void
    {
        foreach ($lines as $index => $line) {
            if ($index === 0) {
                // Replace top border characters with connector characters
                $line = $this->convertTopBorderToConnector($line);
            }
            // Apply gray color to border characters only
            $this->line(' ' . $this->colorBorderCharacters($line));
        }
    }

    /**
     * Apply gray color to Unicode box-drawing border characters.
     */
    private function colorBorderCharacters(string $line): string
    {
        $borderChars = ['─', '│', '┌', '┐', '└', '┘', '├', '┤', '┬', '┴', '┼'];

        foreach ($borderChars as $char) {
            $line = str_replace($char, $this->gray($char), $line);
        }

        return $line;
    }

    /**
     * Convert top border to connector row (├ instead of ┌, ┬ stays as ┬).
     */
    private function convertTopBorderToConnector(string $line): string
    {
        // Replace only corner characters, keep ┬ as is (T-junction down into columns)
        $replacements = [
            '┌' => '├',
            '┐' => '┤',
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $line,
        );
    }
}
