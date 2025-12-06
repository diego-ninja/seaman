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
        // Render the data table first to get its width
        $dataOutput = $this->renderDataTable($table);
        $lines = explode(PHP_EOL, trim($dataOutput));

        if ($lines === ['']) {
            return (string) $this;
        }

        // Calculate table width from rendered output
        $tableWidth = mb_strwidth($this->stripEscapeSequences($lines[0]));

        // If we have header lines, check if they need more width
        if (count($table->headerLines) > 0) {
            $headerContentWidth = $this->longest($table->headerLines, 2); // +2 for padding
            $requiredTableWidth = $headerContentWidth + 2; // +2 for borders

            if ($requiredTableWidth > $tableWidth) {
                // Re-render table with minimum column widths to accommodate header
                $dataOutput = $this->renderDataTableWithMinWidth($table, $requiredTableWidth);
                $lines = explode(PHP_EOL, trim($dataOutput));
                $tableWidth = mb_strwidth($this->stripEscapeSequences($lines[0]));
            }

            $this->renderHeaderSection($table->headerLines, $tableWidth);
            $this->renderDataWithoutTopBorder($lines);
        } else {
            foreach ($lines as $line) {
                $this->line(' ' . $line);
            }
        }

        return (string) $this;
    }

    /**
     * Render the data table using Symfony Table.
     */
    private function renderDataTable(Table $table): string
    {
        return $this->renderDataTableWithMinWidth($table, 0);
    }

    /**
     * Render the data table with a minimum total width.
     */
    private function renderDataTableWithMinWidth(Table $table, int $minWidth): string
    {
        $style = $this->createTableStyle();

        $buffered = new BufferedConsoleOutput();
        $symfonyTable = new SymfonyTable($buffered);
        $symfonyTable->setStyle($style);

        if (count($table->headers) > 0) {
            $symfonyTable->setHeaders($table->headers);

            // If we need extra width, distribute it to the last column
            if ($minWidth > 0) {
                $columnCount = count($table->headers);
                // Approximate current width per column (rough estimate)
                // We'll set minimum width on the last column to absorb extra space
                $extraWidth = $minWidth - ($columnCount * 10); // rough base
                if ($extraWidth > 0) {
                    $symfonyTable->setColumnWidth($columnCount - 1, (int) ($extraWidth / 2));
                }
            }
        }

        foreach ($table->rows as $row) {
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
     */
    private function createTableStyle(): TableStyle
    {
        return (new TableStyle())
            ->setHorizontalBorderChars('─')
            ->setVerticalBorderChars('│', '│')
            ->setCrossingChars('┼', '┌', '┬', '┐', '┤', '┘', '┴', '└', '├')
            ->setCellHeaderFormat('<fg=default;options=bold>%s</>')
            ->setCellRowFormat('<fg=default>%s</>');
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

        // Header lines
        foreach ($headerLines as $line) {
            $paddedLine = $this->pad($line, $contentWidth);
            $this->line(' ' . $this->gray('│') . $paddedLine . $this->gray('│'));
        }
    }

    /**
     * Render data table lines, replacing top border with connector.
     *
     * @param list<string> $lines
     */
    private function renderDataWithoutTopBorder(array $lines): void
    {
        foreach ($lines as $index => $line) {
            if ($index === 0) {
                // Replace top border characters with connector characters
                $line = $this->convertTopBorderToConnector($line);
            }
            $this->line(' ' . $line);
        }
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
