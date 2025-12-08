<?php

declare(strict_types=1);

// ABOUTME: Trait for tests that need to interact with prompts.
// ABOUTME: Provides helpers to set preset responses in headless mode.

namespace Seaman\Tests\Support;

use Seaman\UI\HeadlessMode;

trait InteractsWithPrompts
{
    /**
     * Set specific responses for prompts.
     *
     * @param array<string, mixed> $responses
     */
    protected function setPromptResponses(array $responses): void
    {
        HeadlessMode::enable();
        HeadlessMode::preset($responses);
    }

    /**
     * Use default values for all prompts.
     */
    protected function useDefaults(): void
    {
        HeadlessMode::enable();
    }

    /**
     * Reset headless mode (call in afterEach).
     */
    protected function resetPrompts(): void
    {
        HeadlessMode::reset();
    }
}
