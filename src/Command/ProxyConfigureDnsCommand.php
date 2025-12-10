<?php

declare(strict_types=1);

// ABOUTME: Configures DNS for local development domains.
// ABOUTME: Provides automatic setup for dnsmasq/systemd-resolved or manual instructions.

namespace Seaman\Command;

use Exception;
use Seaman\Contract\Decorable;
use Seaman\Enum\DnsProvider;
use Seaman\Enum\OperatingMode;
use Seaman\Service\ConfigManager;
use Seaman\Service\DnsManager;
use Seaman\Service\Process\RealCommandExecutor;
use Seaman\UI\Prompts;
use Seaman\UI\Terminal;
use Seaman\ValueObject\DnsConfigurationResult;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'proxy:configure-dns',
    description: 'Configure DNS for Traefik local domains',
    aliases: ['dns'],
)]
class ProxyConfigureDnsCommand extends ModeAwareCommand implements Decorable
{
    public function __construct(
        private readonly ConfigManager $configManager,
    ) {
        parent::__construct();
    }

    public function supportsMode(OperatingMode $mode): bool
    {
        return $mode === OperatingMode::Managed;
    }

    protected function configure(): void
    {
        $this->addOption(
            'auto',
            'a',
            InputOption::VALUE_NONE,
            'Automatically configure DNS using the best available method',
        );
        $this->addOption(
            'provider',
            'p',
            InputOption::VALUE_REQUIRED,
            'Specify DNS provider (dnsmasq, systemd-resolved, networkmanager, hosts-file, manual)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $config = $this->configManager->load();
        } catch (Exception $e) {
            Terminal::error('Failed to load configuration: ' . $e->getMessage());
            Terminal::output()->writeln('  Run \'seaman init\' first to initialize the project.');
            return Command::FAILURE;
        }

        $projectName = $config->projectName;
        $services = $config->services;

        Terminal::output()->writeln('');
        Terminal::output()->writeln("  <fg=cyan>DNS Configuration for {$projectName}</>");
        Terminal::output()->writeln('');

        $executor = new RealCommandExecutor();
        $helper = new DnsManager($executor);

        // Handle --auto flag
        $auto = $input->getOption('auto');
        /** @var string|null $providerOption */
        $providerOption = $input->getOption('provider');

        if ($auto || $providerOption !== null) {
            return $this->handleAutomaticMode($helper, $projectName, $providerOption, $services);
        }

        // Interactive mode - let user choose
        return $this->handleInteractiveMode($helper, $projectName, $services);
    }

    private function handleAutomaticMode(
        DnsManager $helper,
        string $projectName,
        ?string $providerOption,
        \Seaman\ValueObject\ServiceCollection $services,
    ): int {
        // Determine which provider to use
        if ($providerOption !== null) {
            try {
                $provider = DnsProvider::from($providerOption);
            } catch (\ValueError) {
                Terminal::error("Invalid provider: {$providerOption}");
                Terminal::output()->writeln('  Valid providers: dnsmasq, systemd-resolved, networkmanager, hosts-file, manual');
                return Command::FAILURE;
            }
        } else {
            $recommended = $helper->getRecommendedProvider($projectName);
            if ($recommended === null) {
                Terminal::error('No DNS providers available for automatic configuration');
                return Command::FAILURE;
            }
            $provider = $recommended->provider;
            Terminal::output()->writeln("  Using best available method: <fg=green>{$provider->getDisplayName()}</>");
            Terminal::output()->writeln('');
        }

        $result = $helper->configureProvider($projectName, $provider, $services);

        if ($result->automatic) {
            return $this->applyConfiguration($result, $projectName, $provider) ? Command::SUCCESS : Command::FAILURE;
        }

        $this->handleManualConfiguration($result);
        return Command::SUCCESS;
    }

    private function handleInteractiveMode(
        DnsManager $helper,
        string $projectName,
        \Seaman\ValueObject\ServiceCollection $services,
    ): int {
        // Detect available providers
        $providers = $helper->detectAvailableProviders($projectName);

        if (empty($providers)) {
            Terminal::output()->writeln('  <fg=yellow>No automatic DNS configuration methods detected.</>');
            Terminal::output()->writeln('');
            $result = $helper->configureProvider($projectName, DnsProvider::Manual, $services);
            $this->handleManualConfiguration($result);
            return Command::SUCCESS;
        }

        // Show available options
        Terminal::output()->writeln('  <fg=white>Available DNS configuration methods:</>');
        Terminal::output()->writeln('');

        $options = ['auto' => 'Auto (recommended) - Let Seaman choose the best method'];
        foreach ($providers as $detected) {
            $label = $detected->provider->getDisplayName();
            $description = $detected->provider->getDescription();
            $options[$detected->provider->value] = "{$label} - {$description}";
        }
        $options['manual'] = 'Manual - Show instructions to configure manually';
        $options['skip'] = 'Skip - Do not configure DNS';

        /** @var string $choice */
        $choice = Prompts::select(
            label: 'How would you like to configure DNS?',
            options: $options,
            default: 'auto',
        );

        if ($choice === 'skip') {
            Terminal::output()->writeln('');
            Prompts::info('DNS configuration skipped.');
            return Command::SUCCESS;
        }

        $provider = null;
        if ($choice === 'auto') {
            $recommended = $helper->getRecommendedProvider($projectName);
            if ($recommended === null) {
                Terminal::error('No providers available');
                return Command::FAILURE;
            }
            $provider = $recommended->provider;
            Terminal::output()->writeln('');
            Terminal::output()->writeln("  Selected: <fg=green>{$provider->getDisplayName()}</>");
            $result = $helper->configureProvider($projectName, $provider, $services);
        } elseif ($choice === 'manual') {
            $provider = DnsProvider::Manual;
            $result = $helper->configureProvider($projectName, $provider, $services);
        } else {
            $provider = DnsProvider::from($choice);
            $result = $helper->configureProvider($projectName, $provider, $services);
        }

        if ($result->automatic) {
            return $this->applyConfiguration($result, $projectName, $provider) ? Command::SUCCESS : Command::FAILURE;
        }

        $this->handleManualConfiguration($result);
        return Command::SUCCESS;
    }

    /**
     * Apply DNS configuration with user confirmation.
     */
    private function applyConfiguration(DnsConfigurationResult $result, string $projectName, DnsProvider $provider): bool
    {
        // Validate that automatic configuration has required fields
        if ($result->configPath === null || $result->configContent === null) {
            Terminal::error('Invalid automatic configuration: missing path or content');
            return false;
        }

        Terminal::output()->writeln('');
        Terminal::output()->writeln("  Method: <fg=green>{$result->type}</>");
        Terminal::output()->writeln('');

        if ($result->requiresSudo) {
            Terminal::output()->writeln('  <fg=yellow>⚠ This requires administrator (sudo) access</>');
            Terminal::output()->writeln('');
        }

        Terminal::output()->writeln("  Configuration file: <fg=cyan>{$result->configPath}</>");
        Terminal::output()->writeln('');
        Terminal::output()->writeln('  Content to write:');
        Terminal::output()->writeln('  <fg=gray>' . str_replace("\n", "\n  ", trim($result->configContent)) . '</>');
        Terminal::output()->writeln('');

        if (!Prompts::confirm('Apply this DNS configuration?', true)) {
            Prompts::info('DNS configuration cancelled.');
            return true; // Not an error, just cancelled
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
                return false;
            }
        }

        // Write configuration
        $tempFile = tempnam(sys_get_temp_dir(), 'seaman-dns-');
        if ($tempFile === false) {
            Terminal::error('Failed to create temporary file');
            return false;
        }
        file_put_contents($tempFile, $result->configContent);

        $escapedTempFile = escapeshellarg($tempFile);
        $escapedConfigPath = escapeshellarg($result->configPath);

        // For /etc/hosts, append instead of overwrite
        if ($result->type === 'hosts-file') {
            $writeCmd = $result->requiresSudo
                ? "cat {$escapedTempFile} | sudo tee -a {$escapedConfigPath} > /dev/null"
                : "cat {$escapedTempFile} >> {$escapedConfigPath}";
        } else {
            $writeCmd = $result->requiresSudo
                ? "sudo cp {$escapedTempFile} {$escapedConfigPath}"
                : "cp {$escapedTempFile} {$escapedConfigPath}";
        }

        exec($writeCmd, $output, $exitCode);
        unlink($tempFile);

        if ($exitCode !== 0) {
            Terminal::error('Failed to write DNS configuration');
            return false;
        }

        Terminal::output()->writeln('');
        Terminal::output()->writeln('  <fg=green>✓</> DNS configuration written');

        // Restart DNS service if needed
        if ($result->restartCommand !== null) {
            Terminal::output()->writeln('');
            Terminal::output()->writeln('  Restarting DNS service...');
            exec($result->restartCommand, $output, $exitCode);
            if ($exitCode !== 0) {
                Terminal::output()->writeln('  <fg=yellow>⚠ Service restart failed. You may need to restart manually.</>');
            }
        }

        Terminal::output()->writeln('');
        Terminal::success('DNS configured successfully!');

        // Save DNS provider to configuration for cleanup
        $this->saveDnsProvider($provider);

        Terminal::output()->writeln('');
        Terminal::output()->writeln('  Your services are now accessible at:');
        Terminal::output()->writeln("  • https://app.{$projectName}.local");
        Terminal::output()->writeln("  • https://traefik.{$projectName}.local");
        Terminal::output()->writeln('');

        return true;
    }

    /**
     * Save DNS provider to seaman configuration.
     */
    private function saveDnsProvider(DnsProvider $provider): void
    {
        try {
            $config = $this->configManager->load();
            $proxy = $config->proxy();

            // Create new proxy config with DNS provider
            $newProxy = new \Seaman\ValueObject\ProxyConfig(
                enabled: $proxy->enabled,
                domainPrefix: $proxy->domainPrefix,
                certResolver: $proxy->certResolver,
                dashboard: $proxy->dashboard,
                dnsProvider: $provider,
            );

            // Create new configuration with updated proxy
            $newConfig = new \Seaman\ValueObject\Configuration(
                projectName: $config->projectName,
                version: $config->version,
                php: $config->php,
                services: $config->services,
                volumes: $config->volumes,
                projectType: $config->projectType,
                proxy: $newProxy,
                customServices: $config->customServices,
            );

            $this->configManager->save($newConfig);
        } catch (\Exception) {
            // Silently ignore - DNS is configured, just couldn't save provider
        }
    }

    private function handleManualConfiguration(DnsConfigurationResult $result): void
    {
        Terminal::output()->writeln('  <fg=yellow>Manual DNS Configuration Required</>');
        Terminal::output()->writeln('');

        foreach ($result->instructions as $instruction) {
            Terminal::output()->writeln('  ' . $instruction);
        }

        Terminal::output()->writeln('');
    }
}
