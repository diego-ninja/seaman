<?php

// ABOUTME: String manipulation utilities for terminal output rendering.
// ABOUTME: Handles ANSI escape sequences, padding, and text length calculations.

declare(strict_types=1);

namespace Seaman\UI\Widget\Box\Concerns;

trait InteractsWithStrings
{
    protected int $minWidth = 60;

    /**
     * Get the length of the longest line.
     *
     * @param array<string> $lines
     */
    protected function longest(array $lines, int $padding = 0): int
    {
        return max(
            $this->minWidth,
            count($lines) > 0 ? max(array_map(fn($line) => mb_strwidth($this->stripEscapeSequences($line)) + $padding, $lines)) : 0,
        );
    }

    /**
     * Pad text ignoring ANSI escape sequences.
     */
    protected function pad(string $text, int $length, string $char = ' '): string
    {
        $rightPadding = str_repeat($char, max(0, $length - mb_strwidth($this->stripEscapeSequences($text))));

        return "{$text}{$rightPadding}";
    }

    /**
     * Strip ANSI escape sequences from the given text.
     */
    protected function stripEscapeSequences(string $text): string
    {
        $text = preg_replace("/\e[^m]*m/", '', $text) ?? $text;
        $text = preg_replace("/<(info|comment|question|error)>(.*?)<\/\\1>/", '$2', $text) ?? $text;

        return preg_replace("/<(?:(?:[fb]g|options)=[a-z,;]+)+>(.*?)<\/>/i", '$1', $text) ?? $text;
    }
}
