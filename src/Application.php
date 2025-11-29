<?php

declare(strict_types=1);

// ABOUTME: Main Symfony Console Application for Seaman.
// ABOUTME: Registers and manages all CLI commands.

namespace Seaman;

use Seaman\Command\BuildCommand;
use Seaman\Command\DbDumpCommand;
use Seaman\Command\DbRestoreCommand;
use Seaman\Command\DbShellCommand;
use Seaman\Command\ExecuteComposerCommand;
use Seaman\Command\ExecuteConsoleCommand;
use Seaman\Command\DestroyCommand;
use Seaman\Command\InitCommand;
use Seaman\Command\LogsCommand;
use Seaman\Command\ExecutePhpCommand;
use Seaman\Command\RebuildCommand;
use Seaman\Command\RestartCommand;
use Seaman\Command\ServiceAddCommand;
use Seaman\Command\ServiceListCommand;
use Seaman\Command\ServiceRemoveCommand;
use Seaman\Command\ShellCommand;
use Seaman\Command\StartCommand;
use Seaman\Command\StatusCommand;
use Seaman\Command\StopCommand;
use Seaman\Command\XdebugCommand;
use Seaman\Service\ConfigManager;
use Seaman\Service\Container\ElasticsearchService;
use Seaman\Service\Container\MailpitService;
use Seaman\Service\Container\MariadbService;
use Seaman\Service\Container\MinioService;
use Seaman\Service\Container\MysqlService;
use Seaman\Service\Container\PostgresqlService;
use Seaman\Service\Container\RabbitmqService;
use Seaman\Service\Container\RedisService;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\Service\DockerManager;
use Seaman\Service\ProjectBootstrapper;
use Seaman\Service\SymfonyDetector;
use Seaman\EventListener\ListenerDiscovery;
use Seaman\EventListener\EventListenerMetadata;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    private EventDispatcher $eventDispatcher;

    public function __construct()
    {
        parent::__construct('Seaman', '1.0.0');

        // Setup EventDispatcher with auto-discovered listeners
        $this->eventDispatcher = $this->createEventDispatcher();
        $this->setDispatcher($this->eventDispatcher);

        $projectRoot = getcwd();
        if ($projectRoot === false) {
            throw new \RuntimeException('Unable to determine current working directory');
        }

        $configManager = new ConfigManager($projectRoot);
        $registry = $this->createServiceRegistry();

        $dockerManager = new DockerManager($projectRoot);

        $commands = [
            new ServiceListCommand($configManager, $registry),
            new ServiceAddCommand($configManager, $registry),
            new ServiceRemoveCommand($configManager, $registry),
            new InitCommand(
                $registry,
                new SymfonyDetector(),
                new ProjectBootstrapper(),
            ),
            new StartCommand(),
            new StopCommand(),
            new RestartCommand(),
            new StatusCommand(),
            new RebuildCommand(),
            new DestroyCommand(),
            new ShellCommand(),
            new LogsCommand(),
            new XdebugCommand(),
            new ExecuteComposerCommand(),
            new ExecuteConsoleCommand(),
            new ExecutePhpCommand(),
            new DbDumpCommand($configManager, $dockerManager),
            new DbRestoreCommand($configManager, $dockerManager),
            new DbShellCommand($configManager, $dockerManager),
        ];

        // Only register build command when not running from PHAR
        if (!\Phar::running()) {
            $commands[] = new BuildCommand();
        }

        $this->addCommands($commands);
    }

    public function getEventDispatcher(): EventDispatcher
    {
        return $this->eventDispatcher;
    }

    private function createServiceRegistry(): ServiceRegistry
    {
        $registry = new ServiceRegistry();

        $registry->register(new PostgresqlService());
        $registry->register(new MysqlService());
        $registry->register(new MariadbService());
        $registry->register(new RedisService());
        $registry->register(new MailpitService());
        $registry->register(new MinioService());
        $registry->register(new ElasticsearchService());
        $registry->register(new RabbitmqService());

        return $registry;
    }

    private function createEventDispatcher(): EventDispatcher
    {
        $dispatcher = new EventDispatcher();

        // Get listeners based on execution mode
        $listeners = $this->getEventListeners();

        // Register each listener with its priority
        foreach ($listeners as $metadata) {
            $listenerInstance = new $metadata->className();
            $dispatcher->addListener(
                $metadata->event,
                $listenerInstance, // @phpstan-ignore argument.type (Listener is callable via __invoke)
                $metadata->priority,
            );
        }

        return $dispatcher;
    }

    /**
     * Get event listeners based on execution mode.
     *
     * @return list<EventListenerMetadata>
     */
    private function getEventListeners(): array
    {
        if (\Phar::running()) {
            // PHAR: load from precompiled file
            $listenersFile = __DIR__ . '/../config/listeners.php';
            if (file_exists($listenersFile)) {
                /** @var list<EventListenerMetadata> */
                return require $listenersFile;
            }
            return [];
        }

        // Development: auto-discovery
        $discovery = new ListenerDiscovery(__DIR__ . '/Listener');
        return $discovery->discover();
    }
}
