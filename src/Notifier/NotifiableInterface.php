<?php

declare(strict_types=1);

// ABOUTME: Interface for commands that support desktop notifications.
// ABOUTME: Defines methods for success and error notification messages.

namespace Seaman\Notifier;

interface NotifiableInterface
{
    /**
     * Get the success message for notifications.
     *
     * @return string The success message.
     */
    public function getSuccessMessage(): string;

    /**
     * Get the error message for notifications.
     *
     * @return string The error message.
     */
    public function getErrorMessage(): string;
}
