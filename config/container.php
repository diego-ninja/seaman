<?php

declare(strict_types=1);

// ABOUTME: PHP-DI container configuration.
// ABOUTME: Defines dependency injection bindings for all services and commands.

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Seaman\Command\CleanCommand;
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
use Seaman\Service\ConfigManager;
use Seaman\Service\ConfigurationFactory;
use Seaman\Service\ConfigurationValidator;
use Seaman\Service\Container\ServiceRegistry;
use Seaman\Service\Detector\PhpVersionDetector;
use Seaman\Service\Detector\ProjectDetector;
use Seaman\Service\Detector\SymfonyDetector;
use Seaman\Service\DnsConfigurationHelper;
use Seaman\Service\DockerManager;
use Seaman\Service\InitializationSummary;
use Seaman\Service\InitializationWizard;
use Seaman\Service\PortAllocator;
use Seaman\Service\PortChecker;
use Seaman\Service\Process\RealCommandExecutor;
use Seaman\Service\ProjectInitializer;
use Seaman\Service\SymfonyProjectBootstrapper;

use function DI\create;
use function DI\factory;
use function DI\get;

return function (ContainerBuilder $builder): void {
    $builder->addDefinitions([
        // Core parameters
        'projectRoot' => factory(fn(): string => base_path()),

        // Core services
        ServiceRegistry::class => factory(fn(): ServiceRegistry => ServiceRegistry::create()),
        ConfigurationValidator::class => create(ConfigurationValidator::class),
        SymfonyDetector::class => create(SymfonyDetector::class),
        PhpVersionDetector::class => create(PhpVersionDetector::class),
        PortChecker::class => create(PortChecker::class),

        PortAllocator::class => factory(
            fn(ContainerInterface $c): PortAllocator => new PortAllocator(
                $c->get(PortChecker::class),
            ),
        ),
        SymfonyProjectBootstrapper::class => create(SymfonyProjectBootstrapper::class),
        InitializationSummary::class => create(InitializationSummary::class),
        RealCommandExecutor::class => create(RealCommandExecutor::class),

        DnsConfigurationHelper::class => factory(
            fn(ContainerInterface $c): DnsConfigurationHelper => new DnsConfigurationHelper(
                $c->get(RealCommandExecutor::class),
            ),
        ),

        DockerManager::class => factory(
            fn(ContainerInterface $c): DockerManager => new DockerManager($c->get('projectRoot')),
        ),

        ConfigManager::class => factory(
            fn(ContainerInterface $c): ConfigManager => new ConfigManager(
                $c->get('projectRoot'),
                $c->get(ServiceRegistry::class),
                $c->get(ConfigurationValidator::class),
            ),
        ),

        ProjectDetector::class => factory(
            fn(ContainerInterface $c): ProjectDetector => new ProjectDetector(
                $c->get(SymfonyDetector::class),
            ),
        ),

        ConfigurationFactory::class => factory(
            fn(ContainerInterface $c): ConfigurationFactory => new ConfigurationFactory(
                $c->get(ServiceRegistry::class),
            ),
        ),

        InitializationWizard::class => factory(
            fn(ContainerInterface $c): InitializationWizard => new InitializationWizard(
                $c->get(PhpVersionDetector::class),
                $c->get(DnsConfigurationHelper::class),
            ),
        ),

        ProjectInitializer::class => factory(
            fn(ContainerInterface $c): ProjectInitializer => new ProjectInitializer(
                $c->get(ServiceRegistry::class),
            ),
        ),

        // Execute commands - single class, 3 registrations
        ExecuteCommand::class . '.composer' => factory(
            fn(ContainerInterface $c): ExecuteCommand => new ExecuteCommand(
                name: 'exec:composer',
                commandDescription: 'Run composer commands on application container',
                aliases: ['composer'],
                commandPrefix: ['composer'],
                dockerManager: $c->get(DockerManager::class), // @phpstan-ignore argument.type
            ),
        ),

        ExecuteCommand::class . '.console' => factory(
            fn(ContainerInterface $c): ExecuteCommand => new ExecuteCommand(
                name: 'exec:console',
                commandDescription: 'Run symfony console commands on application container',
                aliases: ['console'],
                commandPrefix: ['php', 'bin/console'],
                dockerManager: $c->get(DockerManager::class), // @phpstan-ignore argument.type
            ),
        ),

        ExecuteCommand::class . '.php' => factory(
            fn(ContainerInterface $c): ExecuteCommand => new ExecuteCommand(
                name: 'exec:php',
                commandDescription: 'Run php commands on application container',
                aliases: ['php'],
                commandPrefix: ['php'],
                dockerManager: $c->get(DockerManager::class), // @phpstan-ignore argument.type
            ),
        ),

        // Commands with injected dependencies
        ServiceListCommand::class => factory(
            fn(ContainerInterface $c): ServiceListCommand => new ServiceListCommand(
                $c->get(ConfigManager::class),
                $c->get(ServiceRegistry::class),
            ),
        ),

        ServiceAddCommand::class => factory(
            fn(ContainerInterface $c): ServiceAddCommand => new ServiceAddCommand(
                $c->get(ConfigManager::class),
                $c->get(ServiceRegistry::class),
            ),
        ),

        ServiceRemoveCommand::class => factory(
            fn(ContainerInterface $c): ServiceRemoveCommand => new ServiceRemoveCommand(
                $c->get(ConfigManager::class),
                $c->get(ServiceRegistry::class),
            ),
        ),

        InitCommand::class => factory(
            fn(ContainerInterface $c): InitCommand => new InitCommand(
                $c->get(SymfonyDetector::class),
                $c->get(ProjectDetector::class),
                $c->get(SymfonyProjectBootstrapper::class),
                $c->get(ConfigurationFactory::class),
                $c->get(InitializationSummary::class),
                $c->get(InitializationWizard::class),
                $c->get(ProjectInitializer::class),
            ),
        ),

        DevContainerGenerateCommand::class => factory(
            fn(ContainerInterface $c): DevContainerGenerateCommand => new DevContainerGenerateCommand(
                $c->get(ConfigManager::class),
            ),
        ),

        StartCommand::class => factory(
            fn(ContainerInterface $c): StartCommand => new StartCommand(
                $c->get(PortAllocator::class),
                $c->get(ConfigManager::class),
                $c->get(DockerManager::class),
            ),
        ),

        StopCommand::class => factory(
            fn(ContainerInterface $c): StopCommand => new StopCommand(
                $c->get(DockerManager::class),
            ),
        ),

        RestartCommand::class => factory(
            fn(ContainerInterface $c): RestartCommand => new RestartCommand(
                $c->get(DockerManager::class),
            ),
        ),

        StatusCommand::class => factory(
            fn(ContainerInterface $c): StatusCommand => new StatusCommand(
                $c->get(DockerManager::class),
            ),
        ),

        RebuildCommand::class => factory(
            fn(ContainerInterface $c): RebuildCommand => new RebuildCommand(
                $c->get(ConfigManager::class),
                $c->get(DockerManager::class),
            ),
        ),

        DestroyCommand::class => factory(
            fn(ContainerInterface $c): DestroyCommand => new DestroyCommand(
                $c->get(ConfigManager::class),
                $c->get(DockerManager::class),
            ),
        ),

        CleanCommand::class => factory(
            fn(ContainerInterface $c): CleanCommand => new CleanCommand(
                $c->get(DockerManager::class),
            ),
        ),

        ShellCommand::class => factory(
            fn(ContainerInterface $c): ShellCommand => new ShellCommand(
                $c->get(DockerManager::class),
            ),
        ),

        LogsCommand::class => factory(
            fn(ContainerInterface $c): LogsCommand => new LogsCommand(
                $c->get(DockerManager::class),
            ),
        ),

        XdebugCommand::class => factory(
            fn(ContainerInterface $c): XdebugCommand => new XdebugCommand(
                $c->get(DockerManager::class),
            ),
        ),

        DbDumpCommand::class => factory(
            fn(ContainerInterface $c): DbDumpCommand => new DbDumpCommand(
                $c->get(ConfigManager::class),
                $c->get(DockerManager::class),
                $c->get(ServiceRegistry::class),
            ),
        ),

        DbRestoreCommand::class => factory(
            fn(ContainerInterface $c): DbRestoreCommand => new DbRestoreCommand(
                $c->get(ConfigManager::class),
                $c->get(DockerManager::class),
                $c->get(ServiceRegistry::class),
            ),
        ),

        DbShellCommand::class => factory(
            fn(ContainerInterface $c): DbShellCommand => new DbShellCommand(
                $c->get(ConfigManager::class),
                $c->get(DockerManager::class),
                $c->get(ServiceRegistry::class),
            ),
        ),

        ProxyConfigureDnsCommand::class => factory(
            fn(ContainerInterface $c): ProxyConfigureDnsCommand => new ProxyConfigureDnsCommand(
                $c->get(ConfigManager::class),
            ),
        ),

        ProxyEnableCommand::class => factory(
            fn(ContainerInterface $c): ProxyEnableCommand => new ProxyEnableCommand(
                $c->get(ServiceRegistry::class),
                $c->get(ConfigManager::class),
            ),
        ),

        ProxyDisableCommand::class => factory(
            fn(ContainerInterface $c): ProxyDisableCommand => new ProxyDisableCommand(
                $c->get(ConfigManager::class),
            ),
        ),

        InspectCommand::class => factory(
            fn(ContainerInterface $c): InspectCommand => new InspectCommand(
                $c->get(ConfigManager::class),
                $c->get(DockerManager::class),
            ),
        ),
    ]);
};
