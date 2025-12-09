<?php

declare(strict_types=1);

// ABOUTME: Manages headless mode state for UI components.
// ABOUTME: Detects CI/test environments and stores preset responses.

namespace Seaman\UI;

final class HeadlessMode
{
    private static bool $enabled = false;
    private static bool $forceInteractive = false;
    private static ?bool $detected = null;

    /** @var array<string, mixed> */
    private static array $presetResponses = [];

    /**
     * Enable headless mode explicitly.
     */
    public static function enable(): void
    {
        self::$enabled = true;
    }

    /**
     * Disable headless mode (return to auto-detection).
     */
    public static function disable(): void
    {
        self::$enabled = false;
    }

    /**
     * Force interactive mode even without TTY.
     */
    public static function forceInteractive(bool $force = true): void
    {
        self::$forceInteractive = $force;
    }

    /**
     * Check if running in headless mode.
     */
    public static function isHeadless(): bool
    {
        if (self::$forceInteractive) {
            return false;
        }
        if (self::$enabled) {
            return true;
        }

        return self::detect();
    }

    /**
     * Auto-detect headless mode from environment.
     */
    private static function detect(): bool
    {
        if (self::$detected === null) {
            self::$detected
                = getenv('SEAMAN_HEADLESS') === '1'
                || getenv('CI') === 'true'
                || !stream_isatty(STDIN);
        }

        return self::$detected;
    }

    /**
     * Preset responses for prompts (used in tests).
     *
     * @param array<string, mixed> $responses Map of label => response
     */
    public static function preset(array $responses): void
    {
        self::$presetResponses = array_merge(self::$presetResponses, $responses);
    }

    /**
     * Get preset response for a label.
     */
    public static function getPreset(string $label): mixed
    {
        return self::$presetResponses[$label] ?? null;
    }

    /**
     * Check if a preset exists for a label.
     */
    public static function hasPreset(string $label): bool
    {
        return array_key_exists($label, self::$presetResponses);
    }

    /**
     * Reset all state (call between tests).
     */
    public static function reset(): void
    {
        self::$enabled = false;
        self::$forceInteractive = false;
        self::$detected = null;
        self::$presetResponses = [];
    }
}
