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
use Seaman\Service\DnsManager;
use Seaman\Service\DockerManager;
use Seaman\Service\InitializationSummary;
use Seaman\Service\InitializationWizard;
use Seaman\Service\PortAllocator;
use Seaman\Service\PortChecker;
use Seaman\Service\PrivilegedExecutor;
use Seaman\Service\Process\RealCommandExecutor;
use Seaman\Service\ProjectInitializer;
use Seaman\Service\SymfonyProjectBootstrapper;
use Seaman\Plugin\PluginRegistry;
use Seaman\Plugin\PluginLifecycleDispatcher;
use Seaman\Plugin\PluginTemplateLoader;
use Seaman\Plugin\Extractor\TemplateExtractor;
use Seaman\Command\Plugin\PluginListCommand;
use Seaman\Command\Plugin\PluginInfoCommand;
use Seaman\Command\Plugin\PluginCreateCommand;
use Seaman\Command\Plugin\PluginInstallCommand;
use Seaman\Command\ConfigureCommand;
use Seaman\Service\ConfigurationService;
use Seaman\Service\PackagistClient;

use function DI\create;
use function DI\factory;
use function DI\get;

return function (ContainerBuilder $builder): void {
    $builder->addDefinitions([
        // Core parameters
        'projectRoot' => factory(fn (): string => (string) getcwd()),

        // Core services
        ServiceRegistry::class => factory(
            function (ContainerInterface $c): ServiceRegistry {
                $registry = ServiceRegistry::create();
                $registry->registerPluginServices($c->get(PluginRegistry::class));

                return $registry;
            },
        ),
        ConfigurationValidator::class => create(ConfigurationValidator::class),
        ConfigurationService::class => create(ConfigurationService::class),
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

        PrivilegedExecutor::class => factory(
            fn(ContainerInterface $c): PrivilegedExecutor => new PrivilegedExecutor(
                $c->get(RealCommandExecutor::class),
            ),
        ),

        DnsManager::class => factory(
            fn(ContainerInterface $c): DnsManager => new DnsManager(
                $c->get(RealCommandExecutor::class),
                $c->get(PrivilegedExecutor::class),
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
                $c->get(DnsManager::class),
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

        ConfigureCommand::class => factory(
            fn(ContainerInterface $c): ConfigureCommand => new ConfigureCommand(
                $c->get(ConfigManager::class),
                $c->get(ServiceRegistry::class),
                $c->get(ConfigurationService::class),
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
                $c->get(DnsManager::class),
                $c->get(PluginLifecycleDispatcher::class),
                $c->get('projectRoot'),
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
                $c->get(PluginLifecycleDispatcher::class),
                $c->get('projectRoot'),
            ),
        ),

        StopCommand::class => factory(
            fn(ContainerInterface $c): StopCommand => new StopCommand(
                $c->get(DockerManager::class),
                $c->get(PluginLifecycleDispatcher::class),
                $c->get('projectRoot'),
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
                $c->get(PluginLifecycleDispatcher::class),
                $c->get('projectRoot'),
            ),
        ),

        DestroyCommand::class => factory(
            fn(ContainerInterface $c): DestroyCommand => new DestroyCommand(
                $c->get(ConfigManager::class),
                $c->get(DockerManager::class),
                $c->get(DnsManager::class),
                $c->get(PluginLifecycleDispatcher::class),
                $c->get('projectRoot'),
            ),
        ),

        CleanCommand::class => factory(
            fn(ContainerInterface $c): CleanCommand => new CleanCommand(
                $c->get(DockerManager::class),
                $c->get(ConfigManager::class),
                $c->get(DnsManager::class),
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
                $c->get(ServiceRegistry::class),
            ),
        ),

        // Plugin system
        PluginRegistry::class => factory(
            function (ContainerInterface $c): PluginRegistry {
                $projectRoot = $c->get('projectRoot');
                $pluginConfig = [];

                // Determine bundled plugins directory
                // When running from PHAR, it's inside the archive
                // When running from source, it's at repo root
                $pharPath = \Phar::running(false);
                $bundledPluginsDir = $pharPath !== ''
                    ? $pharPath . '/plugins'
                    : dirname(__DIR__) . '/plugins';

                // Load plugin config directly from YAML to avoid circular dependency
                // (PluginRegistry <- ServiceRegistry <- ConfigManager <- PluginRegistry)
                $yamlPath = $projectRoot . '/.seaman/seaman.yaml';
                if (file_exists($yamlPath)) {
                    try {
                        $content = file_get_contents($yamlPath);
                        if ($content !== false) {
                            /** @var array{plugins?: array<string, array<string, mixed>>} $config */
                            $config = \Symfony\Component\Yaml\Yaml::parse($content);
                            $pluginConfig = $config['plugins'] ?? [];
                        }
                    } catch (\Exception $e) {
                        // Project not initialized or invalid config, use empty config
                    }
                }

                return PluginRegistry::discover(
                    projectRoot: $projectRoot,
                    localPluginsDir: $projectRoot . '/.seaman/plugins',
                    pluginConfig: $pluginConfig,
                    bundledPluginsDir: $bundledPluginsDir,
                );
            },
        ),

        PluginLifecycleDispatcher::class => factory(
            fn(ContainerInterface $c): PluginLifecycleDispatcher => new PluginLifecycleDispatcher(
                $c->get(PluginRegistry::class),
            ),
        ),

        PluginTemplateLoader::class => factory(
            fn(ContainerInterface $c): PluginTemplateLoader => new PluginTemplateLoader(
                $c->get(PluginRegistry::class),
                new TemplateExtractor(),
            ),
        ),

        PackagistClient::class => factory(
            fn(ContainerInterface $c): PackagistClient => new PackagistClient(
                $c->get('projectRoot') . '/.seaman/cache',
            ),
        ),

        PluginListCommand::class => factory(
            fn(ContainerInterface $c): PluginListCommand => new PluginListCommand(
                $c->get(PluginRegistry::class),
                $c->get(PackagistClient::class),
            ),
        ),

        PluginInstallCommand::class => factory(
            fn(ContainerInterface $c): PluginInstallCommand => new PluginInstallCommand(
                $c->get(PackagistClient::class),
                $c->get(PluginRegistry::class),
            ),
        ),

        PluginInfoCommand::class => factory(
            fn(ContainerInterface $c): PluginInfoCommand => new PluginInfoCommand(
                $c->get(PluginRegistry::class),
            ),
        ),

        PluginCreateCommand::class => factory(
            fn(ContainerInterface $c): PluginCreateCommand => new PluginCreateCommand(
                $c->get('projectRoot'),
            ),
        ),
    ]);
};
