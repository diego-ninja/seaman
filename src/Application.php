<?php

declare(strict_types=1);

// ABOUTME: Main Symfony Console Application for Seaman.
// ABOUTME: Registers commands and filters them based on operating mode.

namespace Seaman;

use DI\Container;
use DI\ContainerBuilder;
use DI\DependencyException;
use DI\NotFoundException;
use RuntimeException;
use Seaman\Command\BuildCommand;
use Seaman\Exception\FileNotFoundException;
use Seaman\Command\CleanCommand;
use Seaman\Command\ConfigureCommand;
use Seaman\Command\Database\DbDumpCommand;
use Seaman\Command\Database\DbRestoreCommand;
use Seaman\Command\Database\DbShellCommand;
use Seaman\Command\DestroyCommand;
use Seaman\Command\DevContainerGenerateCommand;
use Seaman\Command\ExecuteCommand;
use Seaman\Command\InitCommand;
use Seaman\Command\InspectCommand;
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
use Seaman\Command\Plugin\PluginListCommand;
use Seaman\Command\Plugin\PluginInfoCommand;
use Seaman\Command\Plugin\PluginCreateCommand;
use Seaman\Command\Plugin\PluginInstallCommand;
use Seaman\Command\Plugin\PluginRemoveCommand;
use Seaman\Command\Plugin\PluginExportCommand;
use Seaman\Contract\ModeAwareInterface;
use Seaman\Enum\OperatingMode;
use Seaman\EventListener\EventListenerMetadata;
use Seaman\EventListener\ListenerDiscovery;
use Seaman\Exception\CommandNotAvailableException;
use Seaman\Plugin\PluginRegistry;
use Seaman\Plugin\Extractor\CommandExtractor;
use Seaman\Service\Detector\ModeDetector;
use Seaman\UI\Terminal;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\EventDispatcher\EventDispatcher;

class Application extends BaseApplication
{
    private const string VERSION = '1.1.2';

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

        $container = $this->buildContainer();
        $commands = $this->resolveCommands($container);

        $this->addCommands($commands);
    }

    private function buildContainer(): Container
    {
        $builder = new ContainerBuilder();

        $configFile = __DIR__ . '/../config/container.php';
        if (!file_exists($configFile)) {
            throw FileNotFoundException::create($configFile, 'Container configuration file not found');
        }

        /** @var callable(ContainerBuilder<Container>): void $configurator */
        $configurator = require $configFile;
        $configurator($builder);

        return $builder->build();
    }

    /**
     * @param Container $container
     * @return list<Command>
     * @throws DependencyException
     * @throws NotFoundException
     */
    private function resolveCommands(Container $container): array
    {
        /** @var list<Command> $commands */
        $commands = [
            $container->get(ServiceListCommand::class),
            $container->get(ServiceAddCommand::class),
            $container->get(ServiceRemoveCommand::class),
            $container->get(ConfigureCommand::class),
            $container->get(InitCommand::class),
            $container->get(DevContainerGenerateCommand::class),
            $container->get(StartCommand::class),
            $container->get(StopCommand::class),
            $container->get(RestartCommand::class),
            $container->get(StatusCommand::class),
            $container->get(RebuildCommand::class),
            $container->get(DestroyCommand::class),
            $container->get(CleanCommand::class),
            $container->get(ShellCommand::class),
            $container->get(LogsCommand::class),
            $container->get(XdebugCommand::class),
            $container->get(ExecuteCommand::class . '.composer'),
            $container->get(ExecuteCommand::class . '.console'),
            $container->get(ExecuteCommand::class . '.php'),
            $container->get(DbDumpCommand::class),
            $container->get(DbRestoreCommand::class),
            $container->get(DbShellCommand::class),
            $container->get(ProxyConfigureDnsCommand::class),
            $container->get(ProxyEnableCommand::class),
            $container->get(ProxyDisableCommand::class),
            $container->get(InspectCommand::class),
            $container->get(PluginListCommand::class),
            $container->get(PluginInfoCommand::class),
            $container->get(PluginCreateCommand::class),
            $container->get(PluginInstallCommand::class),
            $container->get(PluginRemoveCommand::class),
            $container->get(PluginExportCommand::class),
        ];

        // Only register build command when not running from PHAR
        if (!\Phar::running()) {
            $commands[] = new BuildCommand();
        }

        // Register commands from plugins
        /** @var PluginRegistry $pluginRegistry */
        $pluginRegistry = $container->get(PluginRegistry::class);
        $commandExtractor = new CommandExtractor();

        foreach ($pluginRegistry->all() as $loaded) {
            $pluginCommands = $commandExtractor->extract($loaded->instance);
            foreach ($pluginCommands as $command) {
                $commands[] = $command;
            }
        }

        return $commands;
    }

    private function buildApplicationName(): string
    {
        $modeLabel = match ($this->currentMode) {
            OperatingMode::Managed => Terminal::render('<fg=green>M</>'),
            OperatingMode::Unmanaged => Terminal::render('<fg=yellow>U</>'),
            OperatingMode::Uninitialized => Terminal::render('<fg=red>N</>'),
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
