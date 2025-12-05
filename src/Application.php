<?php

declare(strict_types=1);

// ABOUTME: Main Symfony Console Application for Seaman.
// ABOUTME: Registers commands and filters them based on operating mode.

namespace Seaman;

use RuntimeException;
use Seaman\Command\BuildCommand;
use Seaman\Command\Database\DbDumpCommand;
use Seaman\Command\Database\DbRestoreCommand;
use Seaman\Command\Database\DbShellCommand;
use Seaman\Command\DestroyCommand;
use Seaman\Command\DevContainerGenerateCommand;
use Seaman\Command\ExecuteComposerCommand;
use Seaman\Command\ExecuteConsoleCommand;
use Seaman\Command\ExecutePhpCommand;
use Seaman\Command\InitCommand;
use Seaman\Command\LogsCommand;
use Seaman\Command\ProxyConfigureDnsCommand;
use Seaman\Command\ProxyDisableCommand;
use Seaman\Command\ProxyEnableCommand;
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
use Seaman\Contract\ModeAwareInterface;
use Seaman\Enum\OperatingMode;
use Seaman\EventListener\EventListenerMetadata;
use Seaman\EventListener\ListenerDiscovery;
use Seaman\Exception\CommandNotAvailableException;
use Seaman\Service\ConfigManager;
use Seaman\Service\ConfigurationFactory;
use Seaman\Service\ConfigurationValidator;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\Service\Detector\ModeDetector;
use Seaman\Service\Detector\PhpVersionDetector;
use Seaman\Service\Detector\ProjectDetector;
use Seaman\Service\Detector\SymfonyDetector;
use Seaman\Service\DockerManager;
use Seaman\Service\InitializationSummary;
use Seaman\Service\InitializationWizard;
use Seaman\Service\PortChecker;
use Seaman\Service\ProjectInitializer;
use Seaman\Service\SymfonyProjectBootstrapper;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\EventDispatcher\EventDispatcher;

class Application extends BaseApplication
{
    private const string VERSION = '1.0.0-beta';

    public EventDispatcher $eventDispatcher {
        get {
            return $this->eventDispatcher;
        }
    }

    private readonly OperatingMode $currentMode;

    public function __construct()
    {
        $projectRoot = getcwd();
        if ($projectRoot === false) {
            throw new RuntimeException('Unable to determine current working directory');
        }

        $modeDetector = new ModeDetector($projectRoot);
        $this->currentMode = $modeDetector->detect();

        $name = $this->buildApplicationName();
        parent::__construct($name, self::VERSION);

        $this->eventDispatcher = $this->createEventDispatcher();
        $this->setDispatcher($this->eventDispatcher);

        $registry = ServiceRegistry::create();
        $validator = new ConfigurationValidator();
        $configManager = new ConfigManager($projectRoot, $registry, $validator);

        $dockerManager = new DockerManager($projectRoot);

        $phpVersionDetector = new PhpVersionDetector();

        $commands = [
            new ServiceListCommand($configManager, $registry),
            new ServiceAddCommand($configManager, $registry),
            new ServiceRemoveCommand($configManager, $registry),
            new InitCommand(
                new SymfonyDetector(),
                new ProjectDetector(new SymfonyDetector()),
                new SymfonyProjectBootstrapper(),
                new ConfigurationFactory($registry),
                new InitializationSummary(),
                new InitializationWizard($phpVersionDetector),
                new ProjectInitializer($registry),
            ),
            new DevContainerGenerateCommand($registry),
            new StartCommand(new PortChecker(), $configManager),
            new StopCommand(),
            new RestartCommand(),
            new StatusCommand(),
            new RebuildCommand(),
            new DestroyCommand($registry),
            new ShellCommand(),
            new LogsCommand(),
            new XdebugCommand(),
            new ExecuteComposerCommand(),
            new ExecuteConsoleCommand(),
            new ExecutePhpCommand(),
            new DbDumpCommand($configManager, $dockerManager, $registry),
            new DbRestoreCommand($configManager, $dockerManager, $registry),
            new DbShellCommand($configManager, $dockerManager, $registry),
            new ProxyConfigureDnsCommand($registry),
            new ProxyEnableCommand($registry),
            new ProxyDisableCommand($registry),
        ];

        // Only register build command when not running from PHAR
        if (!\Phar::running()) {
            $commands[] = new BuildCommand();
        }

        $this->addCommands($commands);
    }

    private function buildApplicationName(): string
    {
        $modeLabel = match ($this->currentMode) {
            OperatingMode::Managed => 'Managed',
            OperatingMode::Unmanaged => 'Unmanaged',
            OperatingMode::Uninitialized => 'Not Initialized',
        };

        return "🔱 Seaman [{$modeLabel}]";
    }

    /**
     * @return array<string, Command>
     */
    public function all(?string $namespace = null): array
    {
        $allCommands = parent::all($namespace);

        return $this->filterCommandsByMode($allCommands);
    }

    public function find(string $name): Command
    {
        // First check if command exists at all
        $command = parent::find($name);

        // Check if it's filtered out by mode
        if (!$this->commandSupportsCurrentMode($command)) {
            throw CommandNotAvailableException::forCommand($name, $this->currentMode);
        }

        return $command;
    }

    /**
     * @param array<string|int, Command> $commands
     * @return array<string, Command>
     */
    private function filterCommandsByMode(array $commands): array
    {
        $filtered = [];

        foreach ($commands as $name => $command) {
            if ($this->commandSupportsCurrentMode($command)) {
                $commandName = $command->getName();
                if ($commandName !== null) {
                    $filtered[$commandName] = $command;
                }
            }
        }

        return $filtered;
    }

    private function commandSupportsCurrentMode(Command $command): bool
    {
        if ($command instanceof ModeAwareInterface) {
            return $command->supportsMode($this->currentMode);
        }

        // Commands that don't implement ModeAwareInterface are always available
        return true;
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
