<?php

declare(strict_types=1);

namespace Seaman\Notifier;

use Joli\JoliNotif\DefaultNotifier;
use Joli\JoliNotif\Exception\InvalidNotificationException;
use Joli\JoliNotif\Notification;

/**
 * Class Notifier
 *
 * Provides a simple interface for sending notifications.
 */
final class Notifier
{
    private static ?self $instance = null;

    private readonly DefaultNotifier $notifier;

    /**
     * Notifier constructor.
     *
     * @throws InvalidNotificationException
     */
    private function __construct()
    {
        $this->notifier = new DefaultNotifier();
    }

    /**
     * Gets an instance of the Notifier.
     *
     * @return Notifier The Notifier instance.
     */
    public static function getInstance(): self
    {
        if (!self::$instance instanceof Notifier) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Sends a success notification with the specified message.
     *
     * @param string $message The notification message.
     */
    public static function success(string $message): void
    {
        self::getInstance()->notifySuccess($message);
    }

    /**
     * Sends an error notification with the specified message.
     *
     * @param string $message The notification message.
     */
    public static function error(string $message): void
    {
        self::getInstance()->notifyError($message);
    }

    /**
     * Sends a generic notification with the specified message.
     *
     * @param string $message The notification message.
     */
    public static function notify(string $message): void
    {
        self::getInstance()->notifySuccess($message);
    }

    /**
     * Sends a success notification with the specified message.
     *
     * @param string $message The notification message.
     */
    private function notifySuccess(string $message): void
    {
        $this->notifier->send($this->getSuccessNotification($message));
    }

    /**
     * Sends an error notification with the specified message.
     *
     * @param string $message The notification message.
     */
    private function notifyError(string $message): void
    {
        $this->notifier->send($this->getErrorNotification($message));
    }

    /**
     * Gets a success notification with the specified message.
     *
     * @param string $message The notification message.
     *
     * @return Notification The success notification.
     */
    private function getSuccessNotification(string $message): Notification
    {
        return new Notification()
            ->setTitle('Seaman Success')
            ->setBody($message)
            ->setIcon(base_path("assets/notification.png"));
    }

    /**
     * Gets an error notification with the specified message.
     *
     * @param string $message The notification message.
     *
     * @return Notification The error notification.
     */
    private function getErrorNotification(string $message): Notification
    {
        return new Notification()
            ->setTitle('Seaman Error')
            ->setBody($message)
            ->setIcon(base_path("assets/notification.png"));
    }
}
