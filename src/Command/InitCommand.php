<?php

declare(strict_types=1);

// ABOUTME: Interactive initialization command.
// ABOUTME: Creates seaman.yaml and sets up Docker environment.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Enum\DnsProvider;
use Seaman\Enum\OperatingMode;
use Seaman\Enum\PhpVersion;
use Seaman\Enum\ProjectType;
use Seaman\Plugin\LifecycleEventData;
use Seaman\Plugin\PluginLifecycleDispatcher;
use Seaman\Service\ComposeImporter;
use Seaman\Service\ConfigurationFactory;
use Seaman\Service\Detector\ProjectDetector;
use Seaman\Service\Detector\ServiceDetector;
use Seaman\Service\DnsManager;
use Seaman\Service\InitializationSummary;
use Seaman\Service\InitializationWizard;
use Seaman\Service\ProjectInitializer;
use Seaman\Service\SymfonyProjectBootstrapper;
use Seaman\UI\Terminal;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\ImportResult;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\ProxyConfig;
use Seaman\ValueObject\ServiceCollection;
use Seaman\ValueObject\ServiceConfig;
use Seaman\ValueObject\VolumeConfig;
use Seaman\ValueObject\XdebugConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Seaman\UI\Prompts;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'seaman:init',
    description: 'Initialize Seaman configuration interactively',
    aliases: ['init'],
)]
class InitCommand extends ModeAwareCommand implements Decorable
{
    /** @var \Closure(string, string): bool */
    private readonly \Closure $fileCopier;

    public function __construct(
        private readonly ProjectDetector            $projectDetector,
        private readonly SymfonyProjectBootstrapper $bootstrapper,
        private readonly ConfigurationFactory       $configFactory,
        private readonly InitializationSummary      $summary,
        private readonly InitializationWizard       $wizard,
        private readonly ProjectInitializer         $initializer,
        private readonly DnsManager                 $dnsManager,
        private readonly PluginLifecycleDispatcher  $lifecycleDispatcher,
        private readonly string                     $projectRoot,
        ?\Closure                                    $fileCopier = null,
    ) {
        $this->fileCopier = $fileCopier ?? static fn(string $source, string $target): bool => @copy($source, $target);
        parent::__construct();
    }

    public function supportsMode(OperatingMode $mode): bool
    {
        return true; // Init works in all modes
    }

    protected function configure(): void
    {
        $this->addOption(
            'with-devcontainer',
            null,
            InputOption::VALUE_NONE,
            'Generate DevContainer configuration for VS Code',
        );
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = $this->projectRoot;

        // Check if seaman.yaml already exists
        if ($this->projectDetector->hasSeamanConfig($projectRoot)) {
            if (!Prompts::confirm(
                label: 'Seaman already initialized. Overwrite configuration?',
            )) {
                Prompts::info('Initialization cancelled.');
                return Command::SUCCESS;
            }
        }

        // Check for the existing docker-compose.yml-offer import
        if ($this->projectDetector->hasDockerCompose($projectRoot) && !$this->projectDetector->hasSeamanConfig($projectRoot)) {
            $import = $this->handleExistingDockerCompose($projectRoot);

            if ($import !== null) {
                return $this->executeImportFlow(
                    $input,
                    $projectRoot,
                    $import['composePath'],
                    $import['result'],
                );
            }
        }

        // Standard initialization flow
        $target = $this->resolveStandardTarget($projectRoot);
        if ($target === null) {
            return Command::FAILURE;
        }

        return $this->executeStandardFlow(
            $input,
            $target['projectRoot'],
            $target['projectType'],
            $target['requiresBootstrap'],
        );
    }

    /**
     * @throws \Exception
     */
    private function executeStandardFlow(
        InputInterface $input,
        string $projectRoot,
        ProjectType $projectType,
        bool $requiresBootstrap,
    ): int {
        // Run the initialization wizard to collect all choices (including DNS)
        $choices = $this->wizard->run($input, $projectType, $projectRoot);

        // Build configuration from choices
        $config = $this->configFactory->createFromChoices($choices, $projectType);

        // Show configuration summary (now includes DNS)
        $this->summary->display(
            database: $choices->database,
            services: $choices->services,
            phpConfig: $config->php,
            projectType: $projectType,
            devContainer: $choices->generateDevContainer,
            proxyEnabled: $choices->useProxy,
            configureDns: $choices->configureDns,
            dnsProvider: $choices->dnsProvider,
        );

        if (!Prompts::confirm(label: 'Continue with this configuration?')) {
            Terminal::success('Initialization cancelled.');
            return Command::SUCCESS;
        }

        $this->dispatchLifecycleEvent('before:init', $projectRoot);

        if ($requiresBootstrap) {
            $this->bootstrapSymfonyProject($projectType, $projectRoot);
        }

        // Initialize Docker environment
        $this->initializer->initializeDockerEnvironment($config, $projectRoot);

        // Generate DevContainer if requested
        if ($choices->generateDevContainer) {
            $this->initializer->generateDevContainer($projectRoot);
        }

        // Apply DNS configuration if selected
        if ($choices->configureDns && $choices->dnsProvider !== null) {
            $this->applyDnsConfiguration($config->projectName, $choices->dnsProvider);
        }

        Terminal::success('Seaman initialized successfully');
        Terminal::output()->writeln('');
        Terminal::output()->writeln('  Your symfony application will be accessible at:');
        Terminal::output()->writeln("  • https://app.{$config->projectName}.local");

        Terminal::output()->writeln([
            '',
            '  Run \'seaman start\' to start your containers',
            '',
            '  ❤️  Happy coding!',

        ]);

        $this->dispatchLifecycleEvent('after:init', $projectRoot);

        return Command::SUCCESS;
    }

    /**
     * @return array{result: ImportResult, composePath: string}|null
     */
    private function handleExistingDockerCompose(string $projectRoot): ?array
    {
        Terminal::output()->writeln('');
        Terminal::output()->writeln('  <fg=cyan>Existing docker-compose file detected</>');
        Terminal::output()->writeln('');

        /** @var string $choice */
        $choice = Prompts::select(
            label: 'How would you like to proceed?',
            options: [
                'import' => 'Import existing docker-compose.yml',
                'fresh' => 'Create fresh configuration (ignore existing)',
            ],
            default: 'import',
        );

        if ($choice === 'fresh') {
            return null;
        }

        $composePath = $this->projectDetector->getDockerComposePath($projectRoot);
        if ($composePath === null) {
            Terminal::error('Could not find docker-compose file');
            return null;
        }

        $importer = new ComposeImporter(new ServiceDetector());

        try {
            $result = $importer->import($composePath);
        } catch (\RuntimeException $e) {
            Terminal::error('Failed to import: ' . $e->getMessage());
            return null;
        }

        $this->displayImportSummary($result);

        if (!Prompts::confirm(label: 'Import these services?')) {
            return null;
        }

        return [
            'result' => $result,
            'composePath' => $composePath,
        ];
    }

    private function backupDockerCompose(string $composePath): bool
    {
        $backupPath = $composePath . '.backup-' . date('Y-m-d-His');
        $copied = ($this->fileCopier)($composePath, $backupPath);
        if (!$copied) {
            Terminal::error('Failed to create a backup of the existing Docker Compose file.');

            return false;
        }

        Terminal::output()->writeln('');
        Terminal::output()->writeln("  <fg=gray>Original backed up to: {$backupPath}</>");

        return true;
    }

    private function displayImportSummary(ImportResult $result): void
    {
        Terminal::output()->writeln('');

        if ($result->hasRecognizedServices()) {
            Terminal::output()->writeln('  <fg=green>Recognized services (will be managed by seaman):</>');
            foreach ($result->recognized as $name => $recognized) {
                $detected = $recognized->detected;
                Terminal::output()->writeln(sprintf(
                    '    • %s → %s (version: %s, confidence: %s)',
                    $name,
                    $detected->type->value,
                    $detected->version,
                    $detected->confidence->value,
                ));
            }
            Terminal::output()->writeln('');
        }

        if ($result->hasCustomServices()) {
            Terminal::output()->writeln('  <fg=yellow>Custom services (will be preserved as-is):</>');
            foreach ($result->custom->names() as $name) {
                Terminal::output()->writeln("    • {$name}");
            }
            Terminal::output()->writeln('');
        }
    }

    /**
     * @throws \Exception
     */
    private function executeImportFlow(
        InputInterface $input,
        string $projectRoot,
        string $composePath,
        ImportResult $importResult,
    ): int {
        $projectName = basename($projectRoot);

        // Convert recognized services to ServiceConfig
        /** @var array<string, ServiceConfig> $serviceConfigs */
        $serviceConfigs = [];
        foreach ($importResult->recognized as $name => $recognized) {
            $detected = $recognized->detected;
            $composeConfig = $recognized->config;

            // Extract port from compose config if available
            $port = $detected->type->port();
            if (isset($composeConfig['ports']) && is_array($composeConfig['ports'])) {
                $firstPort = $composeConfig['ports'][0] ?? null;
                if (is_string($firstPort) && preg_match('/^(\d+):/', $firstPort, $matches)) {
                    $port = (int) $matches[1];
                }
            }

            $serviceConfigs[$name] = new ServiceConfig(
                name: $name,
                enabled: true,
                type: $detected->type,
                version: $detected->version,
                port: $port,
                additionalPorts: [],
                environmentVariables: [],
            );
        }

        // Create configuration
        $config = new Configuration(
            projectName: $projectName,
            version: '1.0',
            php: new PhpConfig(PhpVersion::Php84, XdebugConfig::default()),
            services: new ServiceCollection($serviceConfigs),
            volumes: new VolumeConfig([]),
            projectType: ProjectType::Existing,
            proxy: ProxyConfig::default($projectName),
            customServices: $importResult->custom,
        );

        // Ask about DNS configuration before summary
        [$configureDns, $dnsProvider] = $this->wizard->selectDnsConfiguration($projectName);

        summary(
            title: 'Configuration Summary',
            icon: '⚙',
            data: [
                'Project' => $projectName,
                'Managed Services' => count($serviceConfigs),
                'Custom Services' => $importResult->custom->count(),
                'DNS' => $configureDns ? ($dnsProvider?->getDisplayName() ?? 'Auto-detect') : 'Skip',
            ],
        );

        if (!Prompts::confirm(label: 'Continue with this configuration?')) {
            Terminal::success('Initialization cancelled.');
            return Command::SUCCESS;
        }

        $this->dispatchLifecycleEvent('before:init', $projectRoot);
        if (!$this->backupDockerCompose($composePath)) {
            return Command::FAILURE;
        }

        // Initialize Docker environment
        $this->initializer->initializeDockerEnvironment($config, $projectRoot);

        // Apply DNS configuration if selected
        if ($configureDns && $dnsProvider !== null) {
            $this->applyDnsConfiguration($config->projectName, $dnsProvider);
        }

        Terminal::success('Seaman initialized with imported configuration');
        Terminal::output()->writeln([
            '',
            '  Run \'seaman start\' to start your containers',
            '',
            '  ❤️  Happy coding!',
        ]);

        $this->dispatchLifecycleEvent('after:init', $projectRoot);

        return Command::SUCCESS;
    }

    /**
     * @return array{projectType: ProjectType, projectRoot: string, requiresBootstrap: bool}|null
     */
    private function resolveStandardTarget(string $projectRoot): ?array
    {
        if ($this->projectDetector->isSymfonyProject($projectRoot)) {
            return [
                'projectType' => $this->projectDetector->detectProjectType($projectRoot),
                'projectRoot' => $projectRoot,
                'requiresBootstrap' => false,
            ];
        }

        if (!Prompts::confirm(label: 'No Symfony application detected. Create new project?')) {
            Prompts::info('Please create a Symfony project first, then run init again.');
            return null;
        }

        $projectType = $this->wizard->selectProjectType();
        $projectName = $this->wizard->getProjectName($projectRoot);
        $targetRoot = $this->isDirectoryEmpty($projectRoot)
            ? $projectRoot
            : $projectRoot . '/' . $projectName;

        if ($targetRoot !== $projectRoot && file_exists($targetRoot)) {
            Terminal::error("Target directory already exists: {$targetRoot}");
            return null;
        }

        return [
            'projectType' => $projectType,
            'projectRoot' => $targetRoot,
            'requiresBootstrap' => true,
        ];
    }

    /**
     * @throws \Exception
     */
    private function bootstrapSymfonyProject(ProjectType $projectType, string $projectRoot): void
    {
        if (!$this->bootstrapper->ensureCliInstalled()) {
            throw new \RuntimeException('Cannot create Symfony project without Symfony CLI.');
        }

        $projectName = basename($projectRoot);
        if (!$this->bootstrapper->bootstrap($projectType, $projectName, dirname($projectRoot))) {
            throw new \RuntimeException('Failed to create Symfony project.');
        }

        chdir($projectRoot);
    }

    private function isDirectoryEmpty(string $directory): bool
    {
        return array_diff(scandir($directory) ?: [], ['.', '..']) === [];
    }

    private function dispatchLifecycleEvent(string $event, string $projectRoot): void
    {
        $this->lifecycleDispatcher->dispatch($event, new LifecycleEventData(
            event: $event,
            projectRoot: $projectRoot,
        ));
    }

    /**
     * Apply DNS configuration for the selected provider.
     */
    private function applyDnsConfiguration(string $projectName, DnsProvider $provider): void
    {
        Terminal::output()->writeln('');
        Terminal::output()->writeln('  <fg=cyan>Configuring DNS...</>');
        Terminal::output()->writeln('');

        // Get configuration preview
        $preview = $this->dnsManager->configureProvider($projectName, $provider);

        // Handle manual configuration
        if (!$preview->automatic) {
            Terminal::output()->writeln('  <fg=yellow>Manual DNS Configuration Required</>');
            Terminal::output()->writeln('');
            foreach ($preview->instructions as $instruction) {
                Terminal::output()->writeln('  ' . $instruction);
            }
            Terminal::output()->writeln('');
            return;
        }

        // Handle case where all entries already exist
        if ($preview->configContent === null && $preview->instructions !== []) {
            foreach ($preview->instructions as $instruction) {
                Terminal::output()->writeln("  {$instruction}");
            }
            Terminal::output()->writeln('');
            Terminal::success('DNS already configured');
            return;
        }

        // Show preview for automatic configuration
        if ($preview->configPath !== null && $preview->configContent !== null) {
            Terminal::output()->writeln("  Detected: <fg=green>{$preview->type}</>");
            Terminal::output()->writeln('');

            if ($preview->requiresSudo) {
                Terminal::output()->writeln('  <fg=yellow>⚠ This configuration requires elevated privileges</>');
                Terminal::output()->writeln('');
            }

            Terminal::output()->writeln("  Configuration file: <fg=cyan>{$preview->configPath}</>");
            Terminal::output()->writeln('');
            Terminal::output()->writeln('  Content:');
            Terminal::output()->writeln('  <fg=gray>' . str_replace("\n", "\n  ", trim($preview->configContent)) . '</>');
            Terminal::output()->writeln('');

            if (!Prompts::confirm('Apply this DNS configuration?', true)) {
                Prompts::info('DNS configuration skipped.');
                return;
            }
        }

        // Apply configuration
        $result = $this->dnsManager->applyDnsConfiguration($projectName, $provider);

        Terminal::output()->writeln('');
        if ($result['success']) {
            foreach ($result['messages'] as $message) {
                Terminal::output()->writeln("  {$message}");
            }
            Terminal::output()->writeln('');
            Terminal::success('DNS configured successfully!');
        } else {
            foreach ($result['messages'] as $message) {
                Terminal::error($message);
            }
        }
    }
}
