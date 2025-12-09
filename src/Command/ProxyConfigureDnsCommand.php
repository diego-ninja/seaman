<?php

declare(strict_types=1);

// ABOUTME: Configures DNS for local development domains.
// ABOUTME: Provides automatic setup for dnsmasq/systemd-resolved or manual instructions.

namespace Seaman\Command;

use Exception;
use Seaman\Contract\Decorable;
use Seaman\Enum\OperatingMode;
use Seaman\Service\ConfigManager;
use Seaman\Service\DnsConfigurationHelper;
use Seaman\Service\Process\RealCommandExecutor;
use Seaman\UI\Prompts;
use Seaman\UI\Terminal;
use Seaman\ValueObject\DnsConfigurationResult;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $config = $this->configManager->load();
        } catch (Exception $e) {
            Terminal::error('Failed to load configuration: ' . $e->getMessage());
            Terminal::output()->writeln('  Run \'seaman init\' first to initialize the project.');
            return Command::FAILURE;
        }

        Terminal::output()->writeln('');
        Terminal::output()->writeln('  <fg=cyan>DNS Configuration for ' . $config->projectName . '</>');
        Terminal::output()->writeln('');

        // Detect DNS configuration method
        $executor = new RealCommandExecutor();
        $helper = new DnsConfigurationHelper($executor);
        $result = $helper->configure($config->projectName);

        if ($result->automatic) {
            $this->handleAutomaticConfiguration($result);
        } else {
            $this->handleManualConfiguration($result);
        }

        return Command::SUCCESS;
    }

    private function handleAutomaticConfiguration(DnsConfigurationResult $result): void
    {
        // Validate that automatic configuration has required fields
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
            Prompts::info('DNS configuration cancelled.');
            return;
        }

        // Create directory if needed
        $configDir = dirname($result->configPath);
        if (!is_dir($configDir)) {
            $mkdirCmd = $result->requiresSudo ? "sudo mkdir -p {$configDir}" : "mkdir -p {$configDir}";
            Terminal::output()->writeln("  Creating directory: {$configDir}");
            exec($mkdirCmd, $output, $exitCode);

            if ($exitCode !== 0) {
                Terminal::error('Failed to create configuration directory');
                return;
            }
        }

        // Write configuration
        $tempFile = tempnam(sys_get_temp_dir(), 'seaman-dns-');
        file_put_contents($tempFile, $result->configContent);

        $cpCmd = $result->requiresSudo
            ? "sudo cp {$tempFile} {$result->configPath}"
            : "cp {$tempFile} {$result->configPath}";

        exec($cpCmd, $output, $exitCode);
        unlink($tempFile);

        if ($exitCode !== 0) {
            Terminal::error('Failed to write DNS configuration');
            return;
        }

        // Restart DNS service
        Terminal::output()->writeln('');
        Terminal::output()->writeln('  <fg=green>✓</> DNS configuration written');
        Terminal::output()->writeln('');

        if ($result->type === 'dnsmasq') {
            Terminal::output()->writeln('  Restarting dnsmasq...');
            $restartCmd = PHP_OS_FAMILY === 'Darwin'
                ? 'sudo brew services restart dnsmasq'
                : 'sudo systemctl restart dnsmasq';
            exec($restartCmd);
        } elseif ($result->type === 'systemd-resolved') {
            Terminal::output()->writeln('  Restarting systemd-resolved...');
            exec('sudo systemctl restart systemd-resolved');
        }

        Terminal::success('DNS configured successfully!');
        Terminal::output()->writeln('');
        Terminal::output()->writeln('  Your services are now accessible at:');
        Terminal::output()->writeln("  • https://app.{$result->configContent}.local");
        Terminal::output()->writeln("  • https://traefik.{$result->configContent}.local");
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
