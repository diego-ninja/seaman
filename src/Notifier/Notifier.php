<?php

declare(strict_types=1);

namespace Ninja\Cosmic\Notifier;

use Joli\JoliNotif\Exception\InvalidNotificationException;
use Joli\JoliNotif\Notification;
use Joli\JoliNotif\Notifier as JoliNotifier;
use Joli\JoliNotif\NotifierFactory;
use Ninja\Cosmic\Environment\Env;
use Ninja\Cosmic\Terminal\Terminal;

/**
 * Class Notifier
 *
 * Provides a simple interface for sending notifications.
 */
class Notifier
{
    private static ?self $instance = null;

    private readonly JoliNotifier $notifier;

    /**
     * Notifier constructor.
     *
     * @throws InvalidNotificationException
     */
    private function __construct()
    {
        $this->notifier = NotifierFactory::create();
    }

    /**
     * Gets an instance of the Notifier.
     *
     * @return Notifier The Notifier instance.
     */
    public static function getInstance(): self
    {
        if (!self::$instance instanceof \Ninja\Cosmic\Notifier\Notifier) {
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
        return (new Notification())
            ->setTitle(ucfirst((string)Env::get("APP_NAME")))
            ->setBody($message)
            ->setIcon(Terminal::getTheme()?->getNotificationIcon() ?? "");
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
        return (new Notification())
            ->setTitle(ucfirst((string)Env::get("APP_NAME")))
            ->setBody($message)
            ->setIcon(Terminal::getTheme()?->getNotificationIcon() ?? "");
    }
}
