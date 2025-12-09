<?php

declare(strict_types=1);

// ABOUTME: Wrapper over Laravel Prompts with headless mode support.
// ABOUTME: Uses preset responses or defaults when not running interactively.

namespace Seaman\UI;

use Seaman\Exception\HeadlessModeException;

final class Prompts
{
    /**
     * Confirm prompt (yes/no).
     */
    public static function confirm(
        string $label,
        bool $default = false,
        string $hint = '',
    ): bool {
        if (HeadlessMode::isHeadless()) {
            if (HeadlessMode::hasPreset($label)) {
                return (bool) HeadlessMode::getPreset($label);
            }

            return $default;
        }

        return \Laravel\Prompts\confirm(
            label: $label,
            default: $default,
            hint: $hint,
        );
    }

    /**
     * Single selection prompt.
     *
     * @param array<string, string>|list<string> $options
     */
    public static function select(
        string $label,
        array $options,
        ?string $default = null,
        string $hint = '',
    ): string {
        if (HeadlessMode::isHeadless()) {
            if (HeadlessMode::hasPreset($label)) {
                $preset = HeadlessMode::getPreset($label);
                if (!is_string($preset)) {
                    throw new \InvalidArgumentException(sprintf(
                        'Preset value for "%s" must be a string, %s given',
                        $label,
                        get_debug_type($preset),
                    ));
                }
                return $preset;
            }
            if ($default === null) {
                throw HeadlessModeException::missingDefault($label);
            }

            return $default;
        }

        $result = \Laravel\Prompts\select(
            label: $label,
            options: $options,
            default: $default,
            hint: $hint,
        );

        if (!is_string($result)) {
            throw new \InvalidArgumentException(sprintf(
                'Select result must be a string, %s given',
                get_debug_type($result),
            ));
        }

        return $result;
    }

    /**
     * Multiple selection prompt.
     *
     * @param array<string, string>|list<string> $options
     * @param list<string> $default
     * @return list<string>
     */
    public static function multiselect(
        string $label,
        array $options,
        array $default = [],
        string $hint = '',
        bool $required = false,
    ): array {
        if (HeadlessMode::isHeadless()) {
            if (HeadlessMode::hasPreset($label)) {
                $preset = HeadlessMode::getPreset($label);

                if (is_array($preset)) {
                    return array_values(array_map(function (mixed $v): string {
                        if (!is_string($v) && !is_int($v) && !is_float($v)) {
                            throw new \InvalidArgumentException(sprintf(
                                'Preset multiselect values must be strings, int, or float, %s given',
                                get_debug_type($v),
                            ));
                        }
                        return (string) $v;
                    }, $preset));
                }

                if (!is_string($preset) && !is_int($preset) && !is_float($preset)) {
                    throw new \InvalidArgumentException(sprintf(
                        'Preset multiselect value must be string, int, or float, %s given',
                        get_debug_type($preset),
                    ));
                }

                return [(string) $preset];
            }

            return $default;
        }

        $result = \Laravel\Prompts\multiselect(
            label: $label,
            options: $options,
            default: $default,
            hint: $hint,
            required: $required,
        );

        return array_values(array_map(fn(mixed $v): string => (string) $v, $result));
    }

    /**
     * Text input prompt.
     */
    public static function text(
        string $label,
        string $default = '',
        string $placeholder = '',
        string $hint = '',
    ): string {
        if (HeadlessMode::isHeadless()) {
            if (HeadlessMode::hasPreset($label)) {
                $preset = HeadlessMode::getPreset($label);
                if (!is_string($preset)) {
                    throw new \InvalidArgumentException(sprintf(
                        'Preset value for "%s" must be a string, %s given',
                        $label,
                        get_debug_type($preset),
                    ));
                }
                return $preset;
            }

            return $default;
        }

        return \Laravel\Prompts\text(
            label: $label,
            default: $default,
            placeholder: $placeholder,
            hint: $hint,
        );
    }

    /**
     * Display info message.
     */
    public static function info(string $message): void
    {
        if (HeadlessMode::isHeadless()) {
            Terminal::output()->writeln("  ℹ {$message}");

            return;
        }

        \Laravel\Prompts\info($message);
    }

    /**
     * Display table.
     *
     * @param list<string> $headers
     * @param list<list<string>> $rows
     */
    public static function table(array $headers, array $rows): void
    {
        if (HeadlessMode::isHeadless()) {
            $output = Terminal::output();
            $output->writeln(implode(' | ', $headers));
            $output->writeln(str_repeat('-', 60));
            foreach ($rows as $row) {
                $output->writeln(implode(' | ', $row));
            }

            return;
        }

        \Laravel\Prompts\table($headers, $rows);
    }
}
