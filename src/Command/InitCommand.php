<?php

declare(strict_types=1);

// ABOUTME: Interactive initialization command.
// ABOUTME: Creates seaman.yaml and sets up Docker environment.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Enum\DnsProvider;
use Seaman\Enum\PhpVersion;
use Seaman\Enum\ProjectType;
use Seaman\Service\ComposeImporter;
use Seaman\Service\ConfigurationFactory;
use Seaman\Service\Detector\ProjectDetector;
use Seaman\Service\Detector\ServiceDetector;
use Seaman\Service\Detector\SymfonyDetector;
use Seaman\Service\DnsConfigurationHelper;
use Seaman\Service\InitializationSummary;
use Seaman\Service\InitializationWizard;
use Seaman\Service\Process\RealCommandExecutor;
use Seaman\Service\ProjectInitializer;
use Seaman\Service\SymfonyProjectBootstrapper;
use Seaman\UI\Terminal;
use Seaman\ValueObject\Configuration;
use Seaman\ValueObject\DnsConfigurationResult;
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
    public function __construct(
        private readonly SymfonyDetector            $detector,
        private readonly ProjectDetector            $projectDetector,
        private readonly SymfonyProjectBootstrapper $bootstrapper,
        private readonly ConfigurationFactory       $configFactory,
        private readonly InitializationSummary      $summary,
        private readonly InitializationWizard       $wizard,
        private readonly ProjectInitializer         $initializer,
    ) {
        parent::__construct();
    }

    public function supportsMode(\Seaman\Enum\OperatingMode $mode): bool
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
        $projectRoot = (string) getcwd();

        // Check if seaman.yaml already exists
        if ($this->projectDetector->hasSeamanConfig($projectRoot)) {
            if (!Prompts::confirm(
                label: 'Seaman already initialized. Overwrite configuration?',
                default: false,
            )) {
                Prompts::info('Initialization cancelled.');
                return Command::SUCCESS;
            }
        }

        // Check for existing docker-compose.yml - offer import
        if ($this->projectDetector->hasDockerCompose($projectRoot) && !$this->projectDetector->hasSeamanConfig($projectRoot)) {
            $importResult = $this->handleExistingDockerCompose($projectRoot);

            if ($importResult !== null) {
                return $this->executeImportFlow($input, $projectRoot, $importResult);
            }
        }

        // Standard initialization flow
        return $this->executeStandardFlow($input, $projectRoot);
    }

    /**
     * @throws \Exception
     */
    private function executeStandardFlow(InputInterface $input, string $projectRoot): int
    {
        // Bootstrap Symfony project if needed
        $projectType = $this->enableSymfonyProject($projectRoot);

        // Run initialization wizard to collect all choices (including DNS)
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

        if ($projectType !== ProjectType::Existing) {
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
        Terminal::output()->writeln([
            '',
            '  Run \'seaman start\' to start your containers',
            '',
            '  ❤️  Happy coding!',

        ]);

        return Command::SUCCESS;
    }

    private function handleExistingDockerCompose(string $projectRoot): ?ImportResult
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

        // Backup original file
        $backupPath = $composePath . '.backup-' . date('Y-m-d-His');
        copy($composePath, $backupPath);
        Terminal::output()->writeln('');
        Terminal::output()->writeln("  <fg=gray>Original backed up to: {$backupPath}</>");

        return $result;
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
    private function executeImportFlow(InputInterface $input, string $projectRoot, ImportResult $importResult): int
    {
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

        return Command::SUCCESS;
    }

    private function enableSymfonyProject(string $projectRoot): ProjectType
    {
        // Check if Symfony project exists
        if (!$this->projectDetector->isSymfonyProject($projectRoot)) {
            $shouldBootstrap = Prompts::confirm(
                label: 'No Symfony application detected. Create new project?',
            );

            if (!$shouldBootstrap) {
                Prompts::info('Please create a Symfony project first, then run init again.');
                exit(Command::FAILURE);
            }

            // Bootstrap new Symfony project - user selects type
            return $this->wizard->selectProjectType();
        }

        // Existing Symfony project - auto-detect type for intelligent defaults
        return $this->projectDetector->detectProjectType($projectRoot);
    }

    /**
     * @throws \Exception
     */
    private function bootstrapSymfonyProject(ProjectType $projectType, string $projectRoot): void
    {
        $detection = $this->detector->detect($projectRoot);
        if (!$detection->isSymfonyProject) {
            // Ensure Symfony CLI is installed before attempting bootstrap
            if (!$this->bootstrapper->ensureCliInstalled()) {
                Terminal::error('Cannot create Symfony project without Symfony CLI.');
                exit(Command::FAILURE);
            }

            $projectName = $this->wizard->getProjectName($projectRoot);

            if (!$this->bootstrapper->bootstrap($projectType, $projectName, dirname($projectRoot))) {
                Terminal::error('Failed to create Symfony project.');
                exit(Command::FAILURE);
            }

            // Change to new project directory
            $projectRoot = dirname($projectRoot) . '/' . $projectName;
            chdir($projectRoot);
        }
    }

    /**
     * Apply DNS configuration for the selected provider.
     */
    private function applyDnsConfiguration(string $projectName, DnsProvider $provider): void
    {
        Terminal::output()->writeln('');
        Terminal::output()->writeln('  <fg=cyan>Configuring DNS...</>');
        Terminal::output()->writeln('');

        $executor = new RealCommandExecutor();
        $helper = new DnsConfigurationHelper($executor);

        $result = $helper->configureProvider($projectName, $provider);
        $this->processDnsResult($result, $projectName);
    }

    private function processDnsResult(DnsConfigurationResult $result, string $projectName): void
    {
        if (!$result->automatic) {
            $this->handleManualDnsConfiguration($result);
            return;
        }

        $this->handleAutomaticDnsConfiguration($result, $projectName);
    }

    private function handleAutomaticDnsConfiguration(DnsConfigurationResult $result, string $projectName): void
    {
        if ($result->configPath === null || $result->configContent === null) {
            Terminal::error('Invalid automatic configuration: missing path or content');
            return;
        }

        Terminal::output()->writeln("  Detected: <fg=green>{$result->type}</>");
        Terminal::output()->writeln('');

        if ($result->requiresSudo) {
            Terminal::output()->writeln('  <fg=yellow>⚠ This configuration requires sudo access</>');
            Terminal::output()->writeln('');
        }

        Terminal::output()->writeln("  Configuration file: <fg=cyan>{$result->configPath}</>");
        Terminal::output()->writeln('');
        Terminal::output()->writeln('  Content:');
        Terminal::output()->writeln('  <fg=gray>' . str_replace("\n", "\n  ", trim($result->configContent)) . '</>');
        Terminal::output()->writeln('');

        if (!Prompts::confirm('Apply this DNS configuration?', true)) {
            Prompts::info('DNS configuration skipped.');
            return;
        }

        // Create directory if needed
        $configDir = dirname($result->configPath);
        $escapedConfigDir = escapeshellarg($configDir);
        if (!is_dir($configDir)) {
            $mkdirCmd = $result->requiresSudo
                ? "sudo mkdir -p {$escapedConfigDir}"
                : "mkdir -p {$escapedConfigDir}";
            Terminal::output()->writeln("  Creating directory: {$configDir}");
            exec($mkdirCmd, $output, $exitCode);

            if ($exitCode !== 0) {
                Terminal::error('Failed to create configuration directory');
                return;
            }
        }

        // Write configuration
        $tempFile = tempnam(sys_get_temp_dir(), 'seaman-dns-');
        if ($tempFile === false) {
            Terminal::error('Failed to create temporary file');
            return;
        }
        file_put_contents($tempFile, $result->configContent);

        $escapedTempFile = escapeshellarg($tempFile);
        $escapedConfigPath = escapeshellarg($result->configPath);
        $cpCmd = $result->requiresSudo
            ? "sudo cp {$escapedTempFile} {$escapedConfigPath}"
            : "cp {$escapedTempFile} {$escapedConfigPath}";

        exec($cpCmd, $output, $exitCode);
        unlink($tempFile);

        if ($exitCode !== 0) {
            Terminal::error('Failed to write DNS configuration');
            return;
        }

        Terminal::output()->writeln('');
        Terminal::output()->writeln('  <fg=green>✓</> DNS configuration written');

        // Restart DNS service if needed
        if ($result->restartCommand !== null) {
            Terminal::output()->writeln('');
            Terminal::output()->writeln('  Restarting DNS service...');
            exec($result->restartCommand);
        }

        Terminal::success('DNS configured successfully!');
        Terminal::output()->writeln('');
        Terminal::output()->writeln('  Your services will be accessible at:');
        Terminal::output()->writeln("  • https://app.{$projectName}.local");
        Terminal::output()->writeln("  • https://traefik.{$projectName}.local");
    }

    private function handleManualDnsConfiguration(DnsConfigurationResult $result): void
    {
        Terminal::output()->writeln('  <fg=yellow>Manual DNS Configuration Required</>');
        Terminal::output()->writeln('');

        foreach ($result->instructions as $instruction) {
            Terminal::output()->writeln('  ' . $instruction);
        }

        Terminal::output()->writeln('');
    }
}
