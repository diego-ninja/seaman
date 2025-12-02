<?php

declare(strict_types=1);

// ABOUTME: Main Symfony Console Application for Seaman.
// ABOUTME: Registers and manages all CLI commands.

namespace Seaman;

use RuntimeException;
use Seaman\Command\BuildCommand;
use Seaman\Command\DbDumpCommand;
use Seaman\Command\DbRestoreCommand;
use Seaman\Command\DbShellCommand;
use Seaman\Command\DestroyCommand;
use Seaman\Command\DevContainerGenerateCommand;
use Seaman\Command\ExecuteComposerCommand;
use Seaman\Command\ExecuteConsoleCommand;
use Seaman\Command\ExecutePhpCommand;
use Seaman\Command\InitCommand;
use Seaman\Command\LogsCommand;
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
use Seaman\EventListener\EventListenerMetadata;
use Seaman\EventListener\ListenerDiscovery;
use Seaman\Service\ConfigManager;
use Seaman\Service\ConfigurationFactory;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\Service\InitializationSummary;
use Seaman\Service\InitializationWizard;
use Seaman\Service\PhpVersionDetector;
use Seaman\Service\ProjectBootstrapper;
use Seaman\Service\ProjectInitializer;
use Seaman\Service\SymfonyDetector;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\EventDispatcher\EventDispatcher;

class Application extends BaseApplication
{
    public EventDispatcher $eventDispatcher {
        get {
            return $this->eventDispatcher;
        }
    }

    public function __construct()
    {
        parent::__construct('🔱 Seaman', '1.0.0');

        // Setup EventDispatcher with auto-discovered listeners
        $this->eventDispatcher = $this->createEventDispatcher();
        $this->setDispatcher($this->eventDispatcher);

        $projectRoot = getcwd();
        if ($projectRoot === false) {
            throw new RuntimeException('Unable to determine current working directory');
        }

        $registry = ServiceRegistry::create();
        $configManager = new ConfigManager($projectRoot, $registry);

        // $dockerManager = new DockerManager($projectRoot);

        $phpVersionDetector = new PhpVersionDetector();

        $commands = [
            new ServiceListCommand($configManager, $registry),
            new ServiceAddCommand($configManager, $registry),
            new ServiceRemoveCommand($configManager, $registry),
            new InitCommand(
                new SymfonyDetector(),
                new ProjectBootstrapper(),
                new ConfigurationFactory($registry),
                new InitializationSummary(),
                new InitializationWizard($phpVersionDetector),
                new ProjectInitializer($registry),
            ),
            new DevContainerGenerateCommand($registry),
            new StartCommand(),
            new StopCommand(),
            new RestartCommand(),
            new StatusCommand($registry),
            new RebuildCommand(),
            new DestroyCommand(),
            new ShellCommand(),
            new LogsCommand(),
            new XdebugCommand(),
            new ExecuteComposerCommand(),
            new ExecuteConsoleCommand(),
            new ExecutePhpCommand(),
            //new DbDumpCommand($configManager, $dockerManager),
            //new DbRestoreCommand($configManager, $dockerManager),
            //new DbShellCommand($configManager, $dockerManager),
        ];

        // Only register build command when not running from PHAR
        if (!\Phar::running()) {
            $commands[] = new BuildCommand();
        }

        $this->addCommands($commands);
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
