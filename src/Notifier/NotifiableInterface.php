<?php

declare(strict_types=1);

namespace Seaman\Notifier;

/**
 * Interface NotifiableInterface
 *
 * Defines an interface for objects that can provide success and error messages for notifications.
 */
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
