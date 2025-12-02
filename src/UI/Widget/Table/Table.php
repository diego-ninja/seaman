<?php

declare(strict_types=1);

namespace Seaman\UI\Widget\Table;

use Seaman\UI\Terminal;
use Seaman\UI\Widget\Table\Column\ColumnCollection;
use Seaman\UI\Widget\Table\Column\Column;
use Seaman\UI\Widget\Table\TableConfig;
use Symfony\Component\Console\Output\OutputInterface;

use function Cosmic\pluralize;

class Table
{
    protected ColumnCollection $columns {
        get {
            return $this->columns;
        }
    }

    /**
     * @param array<array<string, string>> $data
     * @param Column[]|null $columns
     */
    public function __construct(
        protected array $data,
        ?array $columns,
        protected TableConfig $config,
        protected ?string $title = null,
    ) {
        $this->columns = $columns ? new ColumnCollection($columns) : new ColumnCollection();
    }

    /**
     * @param array<array<string, string>> $data
     */
    public function injectData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @return array<array<string, string>>|null
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    public function addColumn(Column $field): self
    {
        $this->columns->add($field);
        return $this;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function addRow(array $row): self
    {
        $this->data[] = $row;
        return $this;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function display(?OutputInterface $output): void
    {
        $output instanceof OutputInterface ? $output->writeln($this->render()) : Terminal::output()->writeln($this->render());
    }

    public function render(): string
    {
        $rowCount      = 0;
        $columnLengths = [];
        $headerData    = [];
        $cellData      = [];

        // Headers
        if ($this->config->getShowHeader()) {
            foreach ($this->columns as $column) {
                /** @var Column $column */
                $headerData[$column->key] = trim($column->name);

                if (!isset($columnLengths[$column->key])) {
                    $columnLengths[$column->key] = 0;
                }
                $columnLengths[$column->key] = max($columnLengths[$column->key], strlen(trim($column->name)));
            }
        }

        // Data
        if ($this->data !== []) {
            foreach ($this->data as $row) {
                // Row
                $cellData[$rowCount] = [];
                foreach ($this->columns as $column) {
                    /** @var Column $column */
                    $key                       = $column->key;
                    $value                     = $column->getManipulators()->apply($row[$key]);
                    $cellData[$rowCount][$key] = $value;

                    // Column Lengths
                    if (!isset($columnLengths[$key])) {
                        $columnLengths[$key] = 0;
                    }

                    $c     = chr(27);
                    $lines = explode("\n", (string) preg_replace("/($c\[(.*?)m)/s", '', $value));
                    foreach ($lines as $line) {
                        $columnLengths[$key] = max($columnLengths[$key], mb_strlen($line));
                    }
                }
                $rowCount++;
            }
        } else {
            return 'There are no ' . $this->getPluralItemName() . PHP_EOL;
        }

        $response = '';

        $screenWidth = \Termwind\terminal()->width();

        // Idea here is we're column the accumulated length of the data
        // Then adding the quantity of column lengths to accommodate for the extra characters
        //     for when vertical pipes are placed between each column
        $dataWidth = mb_strlen($this->getTableTop($columnLengths)) + count($columnLengths);

        // Only try and center when content is less than available space
        if ((($dataWidth / 2) < $screenWidth) && $this->config->getCenterContent()) {
            $padding = str_repeat(' ', (int) ($screenWidth - ($dataWidth / 2)) / 2);
        } else {
            $padding = str_repeat(' ', $this->config->getPadding());
        }

        // Add the title if it exists
        if ($this->title !== null) {
            $titleRow = $padding . $this->getTitleTop($columnLengths);
            $titleRow .= $padding . $this->formatTitleRow($this->title, $columnLengths);
            $response = $titleRow . $response;
        }

        // Now draw the table!
        $response .= $padding . $this->getTableTop($columnLengths);
        if ($this->config->getShowHeader()) {
            $response .= $padding . $this->formatRow($headerData, $columnLengths, true);
            $response .= $padding . $this->getTableSeparator($columnLengths);
        }

        foreach ($cellData as $row) {
            $response .= $padding . $this->formatRow($row, $columnLengths);
        }

        return $response . ($padding . $this->getTableBottom($columnLengths));
    }

    /**
     * @param array<string, string> $rowData
     * @param array<string, int> $columnLengths
     */
    protected function formatRow(array $rowData, array $columnLengths, bool $header = false): string
    {
        $response = '';

        $splitLines = [];
        $maxLines   = 1;
        foreach ($rowData as $key => $line) {
            $splitLines[$key] = explode("\n", $line);
            $maxLines         = max($maxLines, count($splitLines[$key]));
        }

        for ($i = 0; $i < $maxLines; $i++) {
            $response .= $this->getChar('left');

            foreach ($splitLines as $key => $lines) {
                if ($header) {
                    $color = $this->config->getHeaderColor();
                } else {
                    $color = $this->columns->getByKey($key)->color ?? $this->config->getFieldColor();
                }

                $line = $lines[$i] ?? '';

                $c          = chr(27);
                $lineLength = mb_strwidth((string) preg_replace("/($c\[(.*?)m)/", '', $line)) + 1;
                $line       = sprintf(" <%s>%s</%s>", $color, $line, $color);
                $response .= $line;

                for ($x = $lineLength; $x < ($columnLengths[$key] + 2); $x++) {
                    $response .= ' ';
                }
                $response .= $this->getChar('middle');
            }

            $response = substr($response, 0, -3) . $this->getChar('right') . PHP_EOL;
        }

        return $response;
    }

    /**
     * @param array<string, int> $columnLengths
     */
    protected function getTitleTop(array $columnLengths): string
    {
        $color = $this->config->getTableColor();
        $total = array_sum($columnLengths) + count($columnLengths) + 1;

        $response = Terminal::color($color)->apply($this->getChar('top-left'));
        $response .= Terminal::color($color)->apply($this->getChar('top', $total + 2));

        return
            substr($response, 0, -3)
            . Terminal::color($color)->apply($this->getChar('top-right'))
            . PHP_EOL;

    }

    /**
     * @param array<string, int> $columnLengths
     */
    protected function formatTitleRow(string $title, array $columnLengths): string
    {
        $tableColor  = $this->config->getTableColor();
        $headerColor = $this->config->getTitleColor();

        $total = array_sum($columnLengths) + count($columnLengths) + 1;

        $c          = chr(27);
        $lineLength = mb_strwidth((string) preg_replace("/($c\[(.*?)m)/", '', $title)) + 1;

        $response = $this->getChar('left');
        $response .= sprintf(" <%s>%s</%s>", $headerColor, $title, $headerColor);

        for ($x = $lineLength; $x < ($total + 5); $x++) {
            $response .= ' ';
        }

        return
            substr($response, 0, -3)
            . Terminal::color($tableColor)->apply($this->getChar('right'))
            . PHP_EOL;
    }

    /**
     * @param array<string, int> $columnLengths
     */
    protected function getTableTop(array $columnLengths): string
    {
        $color = $this->config->getTableColor();

        $response = $this->title
            ? Terminal::color($color)->apply($this->getChar('left-mid'))
            : Terminal::color($color)->apply($this->getChar('top-left'));

        foreach ($columnLengths as $length) {
            $response .= Terminal::color($color)
                ->apply($this->getChar('top', $length + 2));
            $response .= $this->getChar('top-mid');
        }

        $closeChar = $this->title
            ? $this->getChar('right-mid')
            : $this->getChar('top-right');

        return
            substr($response, 0, -3)
            . Terminal::color($color)->apply($closeChar)
            . PHP_EOL;
    }

    /**
     * @param array<string, int> $columnLengths
     */
    protected function getTableBottom(array $columnLengths): string
    {
        $color = $this->config->getTableColor();

        $response = Terminal::color($color)->apply($this->getChar('bottom-left'));
        foreach ($columnLengths as $length) {
            $response .= Terminal::color($color)
                ->apply($this->getChar('bottom', $length + 2));
            $response .= $this->getChar('bottom-mid');
        }

        return
            substr($response, 0, -3)
            . Terminal::color($color)->apply($this->getChar('bottom-right'))
            . PHP_EOL;
    }

    /**
     * @param array<string, int> $columnLengths
     */
    protected function getTableSeparator(array $columnLengths): string
    {
        $color = $this->config->getTableColor();

        $response = Terminal::color($color)->apply($this->getChar('left-mid'));
        foreach ($columnLengths as $length) {
            $response .= Terminal::color($color)
                ->apply($this->getChar('mid', $length + 2));

            $response .= $this->getChar('mid-mid');
        }

        return
            substr($response, 0, -3)
            . Terminal::color($color)->apply($this->getChar('right-mid'))
            . PHP_EOL;
    }

    protected function getChar(string $type, int $length = 1): string
    {
        $response = '';
        if ($this->config->hasChar($type)) {
            $char     = trim((string) $this->config->getChar($type));
            $response = str_repeat($char, $length);
        }

        return $response;

    }

    protected function getPluralItemName(): string
    {
        $itemName = $this->config->getItemName();

        if (count($this->data) === 1) {
            return $itemName;
        }

        return pluralize($itemName);
    }
}
