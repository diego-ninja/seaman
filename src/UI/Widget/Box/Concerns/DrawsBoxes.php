<?php

// ABOUTME: Box-drawing utilities for terminal output.
// ABOUTME: Creates bordered boxes with Unicode box-drawing characters.

declare(strict_types=1);

namespace Seaman\UI\Widget\Box\Concerns;

use Laravel\Prompts\Prompt;
use Seaman\UI\Widget\Box\BoxRenderer;

trait DrawsBoxes
{
    use InteractsWithStrings;

    /**
     * Draw a box.
     *
     * @return $this
     */
    protected function box(
        string $title,
        string $body,
        string $footer = '',
        string $color = 'gray',
        string $info = '',
    ): static {
        $this->minWidth = min($this->minWidth, Prompt::terminal()->cols() - 6);

        $bodyLines = explode(PHP_EOL, $body);
        $footerLines = array_filter(explode(PHP_EOL, $footer));

        $width = $this->longest(array_merge($bodyLines, $footerLines, [$title]));

        $titleLength = mb_strwidth($this->stripEscapeSequences($title));
        $titleLabel = $titleLength > 0 ? " {$title} " : '';
        $topBorder = str_repeat('─', $width - $titleLength + ($titleLength > 0 ? 0 : 2));

        $colorLeft = $this->colorize(' ┌', $color);
        $colorRight = $this->colorize($topBorder . '┐', $color);
        $this->line("{$colorLeft}{$titleLabel}{$colorRight}");

        foreach ($bodyLines as $line) {
            $leftBorder = $this->colorize(' │', $color);
            $rightBorder = $this->colorize('│', $color);
            $this->line("{$leftBorder} {$this->pad($line, $width)} {$rightBorder}");
        }

        if (count($footerLines) > 0) {
            $separator = $this->colorize(' ├' . str_repeat('─', $width + 2) . '┤', $color);
            $this->line($separator);

            foreach ($footerLines as $line) {
                $leftBorder = $this->colorize(' │', $color);
                $rightBorder = $this->colorize('│', $color);
                $this->line("{$leftBorder} {$this->pad($line, $width)} {$rightBorder}");
            }
        }

        $bottomBorder = $this->colorize(
            ' └' . str_repeat(
                '─',
                $info ? ($width - mb_strwidth($this->stripEscapeSequences($info))) : ($width + 2),
            ) . ($info ? " {$info} " : '') . '┘',
            $color,
        );
        $this->line($bottomBorder);

        return $this;
    }

    /**
     * Apply color to text using the specified color method.
     */
    private function colorize(string $text, string $color): string
    {
        return match ($color) {
            'gray' => $this->gray($text),
            'cyan' => $this->cyan($text),
            'yellow' => $this->yellow($text),
            'red' => $this->red($text),
            'green' => $this->green($text),
            'blue' => $this->blue($text),
            'magenta' => $this->magenta($text),
            'white' => $this->white($text),
            'black' => $this->black($text),
            default => $text,
        };
    }
}
